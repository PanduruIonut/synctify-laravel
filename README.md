# Synctify Backend

Laravel API backend for Synctify - a Spotify music library manager.

## Features

### Authentication
- **Spotify OAuth 2.0**: Secure authentication flow
- **Token Management**: Automatic access token refresh
- **Auth Status Tracking**: Detect and track auth failures

### Sync Operations
- **Liked Songs Sync**: Fetch and store liked songs from Spotify
- **Playlist Sync**: Sync all playlists with original timestamps
- **Background Jobs**: Queue-based sync for large libraries

### Import & Export
- **Import to Liked Songs**: Import from any playlist with duplicate detection
- **Export to JSON/CSV**: Download liked songs in multiple formats

### Real-Time Updates (Pusher)
- **WebSocket Notifications**: Real-time sync status updates via Pusher
- **Private Channels**: User-specific channels for secure broadcasting
- **Events**: SyncLikedSongsCompleted broadcasts sync results to frontend

### Smart Features
- **On This Day**: Query songs added on same date in previous years

## API Endpoints

### Authentication
- POST /api/callback - Spotify OAuth callback
- POST /api/user/refresh_token - Refresh access token
- GET /api/user/auth_status/{id} - Check auth status

### Songs & Playlists  
- GET /api/user/get_liked_songs/{id} - Get liked songs
- GET /api/user/playlists/{id} - Get playlists
- GET /api/user/{id}/export-liked-songs?format=json|csv - Export liked songs
- POST /api/user/{id}/playlist/{playlist_id}/import-to-liked - Import to liked

### Broadcasting
- POST /api/broadcasting/auth - Pusher channel authorization

## Environment Variables

```env
# Pusher Configuration
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=eu
BROADCAST_DRIVER=pusher
```

## Tech Stack
- Laravel 10, MySQL, Redis, Docker, Pusher

## Related
- [Synctify Frontend](https://github.com/PanduruIonut/synctify-nuxt)
