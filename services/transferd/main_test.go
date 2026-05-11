package main

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"
)

func TestTransferdServesLocalByteRanges(t *testing.T) {
	root := t.TempDir()
	if err := os.WriteFile(filepath.Join(root, "video.mp4"), []byte("0123456789"), 0o600); err != nil {
		t.Fatal(err)
	}

	srv := &server{
		signingKey:       []byte("secret"),
		localStorageRoot: root,
	}

	request := httptest.NewRequest(http.MethodGet, "/files?token="+testToken(t, "secret", claims{
		Backend:     "local",
		Key:         "video.mp4",
		Name:        "video.mp4",
		MimeType:    "video/mp4",
		SizeBytes:   10,
		Disposition: "inline; filename=video.mp4",
		ExpiresAt:   time.Now().Add(time.Minute).Unix(),
	}), nil)
	request.Header.Set("Range", "bytes=2-5")
	response := httptest.NewRecorder()

	srv.handleFile(response, request)

	if response.Code != http.StatusPartialContent {
		t.Fatalf("expected 206, got %d", response.Code)
	}

	if body := response.Body.String(); body != "2345" {
		t.Fatalf("expected range body, got %q", body)
	}

	if got := response.Header().Get("Content-Range"); got != "bytes 2-5/10" {
		t.Fatalf("expected content range, got %q", got)
	}
}

func TestTransferdRejectsExpiredTokens(t *testing.T) {
	srv := &server{signingKey: []byte("secret")}
	request := httptest.NewRequest(http.MethodGet, "/files?token="+testToken(t, "secret", claims{
		Backend:   "local",
		Key:       "video.mp4",
		ExpiresAt: time.Now().Add(-time.Minute).Unix(),
	}), nil)
	response := httptest.NewRecorder()

	srv.handleFile(response, request)

	if response.Code != http.StatusForbidden {
		t.Fatalf("expected 403, got %d", response.Code)
	}
}

func TestTransferdRejectsLocalPathTraversal(t *testing.T) {
	root := t.TempDir()
	srv := &server{
		signingKey:       []byte("secret"),
		localStorageRoot: root,
	}
	request := httptest.NewRequest(http.MethodGet, "/files?token="+testToken(t, "secret", claims{
		Backend:   "local",
		Key:       "../secret.txt",
		ExpiresAt: time.Now().Add(time.Minute).Unix(),
	}), nil)
	response := httptest.NewRecorder()

	srv.handleFile(response, request)

	if response.Code != http.StatusBadRequest {
		t.Fatalf("expected 400, got %d", response.Code)
	}
}

func testToken(t *testing.T, signingKey string, claims claims) string {
	t.Helper()

	payload, err := json.Marshal(claims)
	if err != nil {
		t.Fatal(err)
	}

	encodedPayload := base64.RawURLEncoding.EncodeToString(payload)
	mac := hmac.New(sha256.New, []byte(signingKey))
	mac.Write([]byte(encodedPayload))
	signature := base64.RawURLEncoding.EncodeToString(mac.Sum(nil))

	return strings.Join([]string{encodedPayload, signature}, ".")
}
