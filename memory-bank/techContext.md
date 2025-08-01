# Technical Context

## Technology Stack

### Core Technologies

- **PHP 8.1**: Main runtime environment
- **Symfony 6.x**: Web framework and console application
- **Docker**: Containerization for deployment
- **Composer**: PHP dependency management

### External APIs

- **Spotify Web API**: Music data and playlist management
- **Notion API**: Artist list storage and retrieval

### Key Dependencies

- `spotify-web-api-php`: Official Spotify Web API PHP library
- `guzzlehttp/guzzle`: HTTP client for API requests
- `symfony/console`: Console command framework

## Development Setup

### Prerequisites

- Docker and Docker Compose
- PHP 8.1+ (for local development)
- Composer (for local development)

### Environment Variables

```env
NOTION_API_KEY=your_notion_api_key
SPOTIFY_CLIENT_ID=your_spotify_client_id
SPOTIFY_CLIENT_SECRET=your_spotify_client_secret
```

### Docker Configuration

- **Base Image**: PHP 8.1 with Apache
- **CRON Job**: Scheduled to run monthly playlist updates
- **Volume Mounts**: Application code and logs
- **Network**: Isolated container network

## API Integration Patterns

### Spotify API

- **Authentication**: OAuth 2.0 with refresh token support
- **Rate Limits**: 100 requests per second per user
- **Error Handling**: HTTP 429 for rate limits, HTTP 401 for auth issues
- **Pagination**: Offset-based pagination for large datasets

### Notion API

- **Authentication**: Bearer token authentication
- **Data Structure**: Database queries with filter and sort options
- **Response Format**: JSON with nested objects and arrays

## Rate Limiting Implementation

### Current Configuration

- **Max Retries**: 3 attempts per API call
- **Backoff Strategy**: Exponential (retry_after \* attempt_number)
- **Request Delays**: 100ms-1000ms between requests
- **Graceful Degradation**: Skip failed items and continue

### Rate Limit Detection

```php
if ($e->getCode() === 429) {
    $retryAfter = $spotifyApi->getLastResponseHeaders()['Retry-After'] ?? 1;
    $backoffTime = $retryAfter * $retryCount;
    sleep($backoffTime);
}
```

## Deployment Architecture

### Container Structure

```
┌─────────────────────────────────────┐
│           Docker Container          │
├─────────────────────────────────────┤
│  Apache + PHP 8.1 + Extensions     │
├─────────────────────────────────────┤
│  Symfony Application               │
├─────────────────────────────────────┤
│  CRON Scheduler                    │
├─────────────────────────────────────┤
│  Log Files                         │
└─────────────────────────────────────┘
```

### CRON Configuration

```bash
0 1 1 * * /usr/local/bin/php /var/www/html/bin/console spotify:update-playlist >> /var/log/cron.log 2>&1
```

- **Schedule**: First day of each month at 1:00 AM
- **Command**: Symfony console command execution
- **Logging**: Output captured to log file

## Development Workflow

### Local Development

1. Clone repository
2. Install dependencies: `composer install`
3. Set environment variables
4. Run command: `php bin/console spotify:update-playlist`

### Testing

- Manual testing with console output
- Docker container testing
- CRON job simulation

### Monitoring

- Console output for real-time monitoring
- Log files for historical analysis
- Error tracking through exception handling
