package main

import (
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"mime"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"github.com/aws/aws-sdk-go-v2/aws"
	"github.com/aws/aws-sdk-go-v2/config"
	"github.com/aws/aws-sdk-go-v2/credentials"
	"github.com/aws/aws-sdk-go-v2/service/s3"
)

type claims struct {
	Backend     string `json:"backend"`
	Bucket      string `json:"bucket"`
	Key         string `json:"key"`
	Name        string `json:"name"`
	MimeType    string `json:"mime_type"`
	SizeBytes   int64  `json:"size_bytes"`
	Disposition string `json:"disposition"`
	ExpiresAt   int64  `json:"expires_at"`
}

type byteRange struct {
	Start  int64
	End    int64
	Status int
}

type server struct {
	signingKey       []byte
	localStorageRoot string
	s3Client         *s3.Client
}

func main() {
	addr := env("TRANSFERD_ADDR", ":8080")
	signingKey := env("TRANSFERD_SIGNING_KEY", env("APP_KEY", ""))
	if signingKey == "" {
		log.Fatal("TRANSFERD_SIGNING_KEY is required")
	}

	srv, err := newServer(context.Background(), []byte(signingKey))
	if err != nil {
		log.Fatal(err)
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusNoContent)
	})
	mux.HandleFunc("/files", srv.handleFile)
	mux.HandleFunc("/__transferd/files", srv.handleFile)

	httpServer := &http.Server{
		Addr:              addr,
		Handler:           mux,
		ReadHeaderTimeout: 5 * time.Second,
	}

	log.Printf("transferd listening on %s", addr)
	if err := httpServer.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
		log.Fatal(err)
	}
}

func newServer(ctx context.Context, signingKey []byte) (*server, error) {
	region := env("AWS_DEFAULT_REGION", "us-east-1")
	accessKey := env("AWS_ACCESS_KEY_ID", "")
	secretKey := env("AWS_SECRET_ACCESS_KEY", "")

	loadOptions := []func(*config.LoadOptions) error{config.WithRegion(region)}
	if accessKey != "" || secretKey != "" {
		loadOptions = append(loadOptions, config.WithCredentialsProvider(credentials.NewStaticCredentialsProvider(accessKey, secretKey, "")))
	}

	cfg, err := config.LoadDefaultConfig(ctx, loadOptions...)
	if err != nil {
		return nil, err
	}

	client := s3.NewFromConfig(cfg, func(options *s3.Options) {
		if endpoint := env("AWS_ENDPOINT", ""); endpoint != "" {
			options.BaseEndpoint = aws.String(endpoint)
		}
		options.UsePathStyle = envBool("AWS_USE_PATH_STYLE_ENDPOINT", false)
	})

	return &server{
		signingKey:       signingKey,
		localStorageRoot: env("LOCAL_STORAGE_ROOT", "/var/www/html/storage/app/private"),
		s3Client:         client,
	}, nil
}

func (s *server) handleFile(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet && r.Method != http.MethodHead {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, err := s.verifyToken(r.URL.Query().Get("token"))
	if err != nil {
		http.Error(w, "invalid transfer token", http.StatusForbidden)
		return
	}

	switch claims.Backend {
	case "local":
		s.serveLocal(w, r, claims)
	case "s3":
		s.serveS3(w, r, claims)
	default:
		http.Error(w, "unsupported backend", http.StatusBadRequest)
	}
}

func (s *server) verifyToken(token string) (claims, error) {
	parts := strings.Split(token, ".")
	if len(parts) != 2 {
		return claims{}, errors.New("malformed token")
	}

	expected := hmac.New(sha256.New, s.signingKey)
	expected.Write([]byte(parts[0]))
	expectedSignature := expected.Sum(nil)

	actualSignature, err := decode(parts[1])
	if err != nil {
		return claims{}, err
	}

	if !hmac.Equal(actualSignature, expectedSignature) {
		return claims{}, errors.New("signature mismatch")
	}

	payload, err := decode(parts[0])
	if err != nil {
		return claims{}, err
	}

	var parsed claims
	if err := json.Unmarshal(payload, &parsed); err != nil {
		return claims{}, err
	}

	if parsed.ExpiresAt < time.Now().Unix() {
		return claims{}, errors.New("expired token")
	}

	if parsed.Key == "" {
		return claims{}, errors.New("missing object key")
	}

	return parsed, nil
}

func (s *server) serveLocal(w http.ResponseWriter, r *http.Request, claims claims) {
	path, err := s.localPath(claims.Key)
	if err != nil {
		http.Error(w, "invalid local path", http.StatusBadRequest)
		return
	}

	file, err := os.Open(path)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	defer file.Close()

	stat, err := file.Stat()
	if err != nil {
		http.Error(w, "unable to stat file", http.StatusInternalServerError)
		return
	}

	fileRange, ok := parseRange(r.Header.Get("Range"), stat.Size())
	if !ok {
		writeRangeNotSatisfiable(w, stat.Size(), claims)
		return
	}

	writeHeaders(w, claims, stat.Size(), fileRange)
	if r.Method == http.MethodHead {
		w.WriteHeader(fileRange.Status)
		return
	}

	if _, err := file.Seek(fileRange.Start, io.SeekStart); err != nil {
		http.Error(w, "unable to seek file", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(fileRange.Status)
	_, _ = io.CopyN(w, file, fileRange.End-fileRange.Start+1)
}

func (s *server) serveS3(w http.ResponseWriter, r *http.Request, claims claims) {
	if claims.Bucket == "" {
		http.Error(w, "missing bucket", http.StatusBadRequest)
		return
	}

	objectRange, ok := parseRange(r.Header.Get("Range"), claims.SizeBytes)
	if !ok {
		writeRangeNotSatisfiable(w, claims.SizeBytes, claims)
		return
	}

	writeHeaders(w, claims, claims.SizeBytes, objectRange)
	if r.Method == http.MethodHead {
		w.WriteHeader(objectRange.Status)
		return
	}

	input := &s3.GetObjectInput{
		Bucket: aws.String(claims.Bucket),
		Key:    aws.String(claims.Key),
	}
	if objectRange.Status == http.StatusPartialContent {
		input.Range = aws.String(fmt.Sprintf("bytes=%d-%d", objectRange.Start, objectRange.End))
	}

	output, err := s.s3Client.GetObject(r.Context(), input)
	if err != nil {
		http.Error(w, "unable to read object", http.StatusBadGateway)
		return
	}
	defer output.Body.Close()

	w.WriteHeader(objectRange.Status)
	_, _ = io.Copy(w, output.Body)
}

func (s *server) localPath(key string) (string, error) {
	root, err := filepath.Abs(s.localStorageRoot)
	if err != nil {
		return "", err
	}

	cleaned := filepath.Clean(key)
	if filepath.IsAbs(cleaned) || cleaned == ".." || strings.HasPrefix(cleaned, ".."+string(os.PathSeparator)) {
		return "", errors.New("path escapes local storage root")
	}

	candidate, err := filepath.Abs(filepath.Join(root, cleaned))
	if err != nil {
		return "", err
	}

	if candidate != root && !strings.HasPrefix(candidate, root+string(os.PathSeparator)) {
		return "", errors.New("path escapes local storage root")
	}

	return candidate, nil
}

func writeHeaders(w http.ResponseWriter, claims claims, size int64, fileRange byteRange) {
	length := max(fileRange.End-fileRange.Start+1, 0)

	w.Header().Set("Accept-Ranges", "bytes")
	w.Header().Set("Content-Type", contentType(claims))
	w.Header().Set("Content-Disposition", disposition(claims))
	w.Header().Set("Content-Length", strconv.FormatInt(length, 10))
	if fileRange.Status == http.StatusPartialContent {
		w.Header().Set("Content-Range", fmt.Sprintf("bytes %d-%d/%d", fileRange.Start, fileRange.End, size))
	}
}

func writeRangeNotSatisfiable(w http.ResponseWriter, size int64, claims claims) {
	w.Header().Set("Accept-Ranges", "bytes")
	w.Header().Set("Content-Type", contentType(claims))
	w.Header().Set("Content-Disposition", disposition(claims))
	w.Header().Set("Content-Length", "0")
	w.Header().Set("Content-Range", fmt.Sprintf("bytes */%d", size))
	w.WriteHeader(http.StatusRequestedRangeNotSatisfiable)
}

func parseRange(header string, size int64) (byteRange, bool) {
	if size <= 0 {
		return byteRange{Start: 0, End: -1, Status: http.StatusOK}, true
	}

	if header == "" {
		return byteRange{Start: 0, End: size - 1, Status: http.StatusOK}, true
	}

	if !strings.HasPrefix(header, "bytes=") || strings.Contains(header, ",") {
		return byteRange{}, false
	}

	parts := strings.Split(strings.TrimPrefix(header, "bytes="), "-")
	if len(parts) != 2 || (parts[0] == "" && parts[1] == "") {
		return byteRange{}, false
	}

	var start int64
	end := size - 1

	if parts[0] == "" {
		suffix, err := strconv.ParseInt(parts[1], 10, 64)
		if err != nil || suffix <= 0 {
			return byteRange{}, false
		}
		start = max(size-suffix, 0)
	} else {
		parsedStart, err := strconv.ParseInt(parts[0], 10, 64)
		if err != nil || parsedStart < 0 || parsedStart >= size {
			return byteRange{}, false
		}
		start = parsedStart

		if parts[1] != "" {
			parsedEnd, err := strconv.ParseInt(parts[1], 10, 64)
			if err != nil || parsedEnd < start {
				return byteRange{}, false
			}
			end = min(parsedEnd, size-1)
		}
	}

	return byteRange{Start: start, End: end, Status: http.StatusPartialContent}, true
}

func contentType(claims claims) string {
	if claims.MimeType != "" {
		return claims.MimeType
	}

	return "application/octet-stream"
}

func disposition(claims claims) string {
	if claims.Disposition != "" {
		return claims.Disposition
	}

	name := claims.Name
	if name == "" {
		name = "download"
	}

	return mime.FormatMediaType("attachment", map[string]string{"filename": name})
}

func decode(value string) ([]byte, error) {
	return base64.RawURLEncoding.DecodeString(value)
}

func env(key string, fallback string) string {
	value := os.Getenv(key)
	if value == "" {
		return fallback
	}

	return value
}

func envBool(key string, fallback bool) bool {
	value := os.Getenv(key)
	if value == "" {
		return fallback
	}

	parsed, err := strconv.ParseBool(value)
	if err != nil {
		return fallback
	}

	return parsed
}
