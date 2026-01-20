# Synctify Backend

Laravel API backend for Synctify - a Spotify music library manager. Handles authentication, sync operations, and data management.

## Features

### Authentication
- **Spotify OAuth**: Secure OAuth 2.0 authentication with Spotify
- **Token Management**: Automatic access token refresh
- **Auth Status Tracking**: Monitor authentication state and detect failures
- **Failure Recovery**: Track auth failures and notify frontend for re-authorization

### Sync Operations
- **Liked Songs Sync**: Fetch and store users liked songs from Spotify
- **Playlist Sync**: Sync all user playlists with track details
- **Date Preservation**: Maintain original added-at timestamps from Spotify
- **Background Jobs**: Queue-based sync for large libraries

### Data Management
- **Import to Liked Songs**: Import songs from any playlist to liked songs with duplicate detection
- **On This Day**: Find songs added on the same date in previous years

## API Endpoints

### Authentication
- POST /api/callback - Spotify OAuth callback
- POST /api/user/refresh_token - Refresh access token
- GET /api/user/auth_status/{id} - Check authentication status

### Songs & Playlists
- GET /api/user/get_liked_songs/{id} - Get users liked songs
- GET /api/user/playlists/{id} - Get users playlists
- POST /api/user/{id}/playlist/{playlist_id}/import-to-liked - Import playlist to liked songs
- POST /api/user/sync_playlists - Trigger playlist sync

## Tech Stack
- Laravel 10
- MySQL
- Redis (Queue and Cache)
- Docker

## Related
- [Synctify Frontend](https://github.com/PanduruIonut/synctify-nuxt) - Nuxt 3 frontend
