<?php

namespace App\Service;

use Exception;
use JsonException;
use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;

class SpotifyService
{
    private Session $session;
    private SpotifyWebAPI $spotify;
    private SpotifyWebAPIWrapper $spotifyWrapper;
    private string $tokenFile;

    public function __construct()
    {
        $this->session = new Session(
            $_ENV['SPOTIFY_CLIENT_ID'],
            $_ENV['SPOTIFY_CLIENT_SECRET'],
            $_ENV['SPOTIFY_REDIRECT_URI']
        );

        $spotifyApi = new SpotifyWebAPI();
        $this->spotifyWrapper = new SpotifyWebAPIWrapper($spotifyApi);
        $this->tokenFile = __DIR__ . '/../../tokens.json';
    }

    public function generateAuthUrl(array $scopes): string
    {
        return $this->session->getAuthorizeUrl([
            'scope' => $scopes,
        ]);
    }

    /**
     * @throws JsonException
     */
    public function requestAccessToken(string $code): void
    {
        $this->session->requestAccessToken($code);

        $this->saveTokens(
            $this->session->getAccessToken(),
            $this->session->getRefreshToken(),
            $this->session->getTokenExpiration()
        );

        $this->spotify->setAccessToken($this->session->getAccessToken());
    }

    /**
     * @throws JsonException
     */
    private function saveTokens(string $accessToken, string $refreshToken, int $expiresAt): void
    {
        $tokenData = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
        ];

        file_put_contents($this->tokenFile, json_encode($tokenData, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws JsonException
     */
    private function loadTokens(): array
    {
        if (!file_exists($this->tokenFile)) {
            throw new \RuntimeException('Token file not found. Please authenticate.');
        }

        return json_decode(file_get_contents($this->tokenFile), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws Exception
     */
    private function refreshAccessTokenIfNeeded(): void
    {
        $tokens = $this->loadTokens();
        $currentTime = time();

        if ($tokens['expires_at'] <= $currentTime) {
            error_log("Refreshing access token...");

            $this->session->refreshAccessToken($tokens['refresh_token']);
            $newAccessToken = $this->session->getAccessToken();
            $newRefreshToken = $this->session->getRefreshToken() ?? $tokens['refresh_token'];

            $tokens['access_token'] = $newAccessToken;
            $tokens['refresh_token'] = $newRefreshToken;
            $tokens['expires_at'] = $this->session->getTokenExpiration();

            $this->saveTokens($tokens['access_token'], $tokens['refresh_token'], $tokens['expires_at']);
        }

        $this->spotifyWrapper->getSpotifyAPI()->setAccessToken($tokens['access_token']);
    }

    /**
     * @throws Exception
     */
    public function getSpotifyAPI(): SpotifyWebAPIWrapper
    {
        try {
            $tokens = $this->loadTokens();

            $this->session->setAccessToken($tokens['access_token']);
            $this->session->setRefreshToken($tokens['refresh_token']);

            $this->refreshAccessTokenIfNeeded();

            $this->spotifyWrapper->getSpotifyAPI()->setAccessToken($this->session->getAccessToken());

            return $this->spotifyWrapper;
        } catch (Exception $e) {
            throw new \RuntimeException("Error initializing Spotify API: " . $e->getMessage());
        }
    }
    /**
     * Expose the Session object.
     *
     * @return Session
     */
    public function getSession(): Session
    {
        return $this->session;
    }
    /**
     * @throws Exception
     */
    public function createOrUpdatePlaylist(string $playlistName, array $trackUris): string
    {
        $spotify = $this->getSpotifyAPI();
        $userId = $spotify->getSpotifyAPI()->me()->id;

        $playlists = $spotify->getSpotifyAPI()->getUserPlaylists($userId, ['limit' => 50]);
        $playlistId = null;

        foreach ($playlists->items as $playlist) {
            if ($playlist->name === $playlistName) {
                $playlistId = $playlist->id;
                break;
            }
        }

        if (!$playlistId) {
            $playlist = $spotify->getSpotifyAPI()->createPlaylist([
                'name' => $playlistName,
                'description' => 'Monthly playlist generated automatically.',
            ]);
            $playlistId = $playlist->id;
        }

        // Add tracks to the playlist in chunks of 50
        $trackUrisChunks = array_chunk($trackUris, 50);
        foreach ($trackUrisChunks as $chunk) {
            $spotify->getSpotifyAPI()->addPlaylistTracks($playlistId, $chunk);
        }
        //$spotify->replacePlaylistTracks($playlistId, $trackUris);

        return $playlistId;
    }

    /**
     * @throws Exception
     */
    public function refreshAccessToken(): void
    {
        $this->refreshAccessTokenIfNeeded();
    }
}
