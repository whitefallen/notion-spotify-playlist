# System Patterns

## Architecture Overview

The application follows a layered architecture with clear separation of concerns:

```
┌─────────────────┐
│   Console       │  ← Entry point (UpdatePlaylistCommand)
├─────────────────┤
│   Services      │  ← Business logic (NotionService, SpotifyService)
├─────────────────┤
│   API Wrappers  │  ← API abstraction (SpotifyWebAPIWrapper)
├─────────────────┤
│   External APIs │  ← Notion API, Spotify API
└─────────────────┘
```

## Key Design Patterns

### 1. Service Layer Pattern

- **NotionService**: Handles Notion API interactions
- **SpotifyService**: Manages Spotify authentication and high-level operations
- **SpotifyWebAPIWrapper**: Wraps the Spotify Web API library with additional functionality

### 2. Command Pattern

- **UpdatePlaylistCommand**: Encapsulates the playlist update workflow
- Provides clear entry point and error handling
- Supports console output for monitoring

### 3. Retry Pattern (Rate Limiting)

```php
$maxRetries = 3;
$retryCount = 0;

while ($retryCount <= $maxRetries) {
    try {
        // API call
        break; // Success
    } catch (RateLimitException $e) {
        $retryCount++;
        if ($retryCount > $maxRetries) {
            // Graceful failure
            break;
        }
        // Exponential backoff
        sleep($backoffTime);
    }
}
```

## Critical Implementation Paths

### 1. Artist Discovery Flow

```
Notion Database → Artist URIs → Spotify Artist Albums → Track Collection → Filtering → Playlist
```

### 2. Rate Limiting Strategy

- **Detection**: HTTP 429 status codes
- **Response**: Exponential backoff with retry limits
- **Recovery**: Graceful degradation (skip failed items)
- **Prevention**: Request throttling with delays

### 3. Error Handling Hierarchy

1. **Rate Limit Errors**: Retry with backoff
2. **Authentication Errors**: Token refresh and retry
3. **Other API Errors**: Log and skip
4. **Unexpected Errors**: Log and continue

## Component Relationships

### UpdatePlaylistCommand Dependencies

- `SpotifyService`: For API client and authentication
- `NotionService`: For artist data retrieval
- `SpotifyWebAPIWrapper`: For API calls with rate limiting

### Service Responsibilities

- **NotionService**: Database queries, artist list management
- **SpotifyService**: Authentication, playlist operations
- **SpotifyWebAPIWrapper**: API calls, response header tracking

## Technical Decisions

### 1. Rate Limiting Approach

- **Why**: Spotify API has strict rate limits
- **How**: Exponential backoff with maximum retries
- **Result**: Prevents infinite loops and ensures completion

### 2. Batch Processing

- **Why**: Efficiency and rate limit management
- **How**: Process tracks in batches of 50
- **Result**: Reduced API calls and better error isolation

### 3. Graceful Degradation

- **Why**: Ensure playlist creation even with partial failures
- **How**: Skip failed items and continue processing
- **Result**: Robust operation despite API issues
