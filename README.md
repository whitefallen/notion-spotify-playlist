# Notion Spotify Playlist

This is a Symfony PHP project that utilizes Notion as an artist list storage system and the Spotify API to create a monthly playlist based on the Notion artist list. The project is built with PHP 8.1 and can be deployed via Docker.

## Features

- Fetches artists from Notion.
- Uses the Spotify API to find recent tracks for the artists.
- Creates or updates a Spotify playlist for the current month.
- Runs as a scheduled CRON job to update the playlist periodically.

## Requirements

- PHP 8.1
- Docker
- Notion API credentials
- Spotify API credentials

## Installation

1. Clone the repository:
    ```sh
    git clone https://github.com/yourusername/notion-spotify-playlist.git
    cd notion-spotify-playlist
    ```

2. Set up environment variables:
   Create a `.env` file in the root directory and add your Notion and Spotify API credentials.
    ```env
    NOTION_API_KEY=your_notion_api_key
    SPOTIFY_CLIENT_ID=your_spotify_client_id
    SPOTIFY_CLIENT_SECRET=your_spotify_client_secret
    ```

3. Build and run the Docker container:
    ```sh
    docker-compose up --build
    ```

## Usage

The project includes a Symfony console command `spotify:update-playlist` that fetches artists from Notion, finds recent tracks for the artists using the Spotify API, and creates or updates a Spotify playlist for the current month.

The command is scheduled to run as a CRON job, which is configured in the `Dockerfile`:
```sh
0 1 1 * * /usr/local/bin/php /var/www/html/bin/console spotify:update-playlist >> /var/log/cron.log 2>&1
```

## Development

To run the project locally for development purposes:

1. Install dependencies:
    ```sh
    composer install
    ```

2. Run the Symfony server:
    ```sh
    symfony server:start
    ```

3. Execute the command manually:
    ```sh
    php bin/console spotify:update-playlist
    ```

## License

This project is licensed under the MIT License. See the `LICENSE` file for details.