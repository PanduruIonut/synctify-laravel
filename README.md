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

## Tech Stack
- Laravel 10, MySQL, Redis, Docker

## Related
- [Synctify Frontend](https://github.com/PanduruIonut/synctify-nuxt)
