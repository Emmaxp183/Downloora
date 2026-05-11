FROM golang:1.24-alpine AS build

WORKDIR /src

COPY services/transferd/go.mod services/transferd/go.sum* ./
RUN go mod download

COPY services/transferd ./
RUN CGO_ENABLED=0 GOOS=linux go build -o /out/transferd .

FROM alpine:3.22

RUN apk add --no-cache ca-certificates

COPY --from=build /out/transferd /usr/local/bin/transferd

EXPOSE 8080

ENTRYPOINT ["transferd"]
