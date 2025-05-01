<?php

namespace App\Service;

use SpotifyWebAPI\SpotifyWebAPI;
use SpotifyWebAPI\SpotifyWebAPIException;

class SpotifyWebAPIWrapper
{
  private SpotifyWebAPI $spotifyApi;
  private array $lastResponseHeaders = [];

  public function __construct(SpotifyWebAPI $spotifyApi)
  {
    $this->spotifyApi = $spotifyApi;
  }

  public function getArtistAlbums(string $artistId, array $options = []): object
  {
    try {
      $response = $this->spotifyApi->getArtistAlbums($artistId, $options);
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      return $response;
    } catch (SpotifyWebAPIException $e) {
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      throw $e;
    }
  }

  public function getAlbumTracks(string $albumId, array $options = []): object
  {
    try {
      $response = $this->spotifyApi->getAlbumTracks($albumId, $options);
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      return $response;
    } catch (SpotifyWebAPIException $e) {
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      throw $e;
    }
  }

  public function getTracks(array $trackIds): object
  {
    try {
      $response = $this->spotifyApi->getTracks($trackIds);
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      return $response;
    } catch (SpotifyWebAPIException $e) {
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      throw $e;
    }
  }

  public function getUserPlaylists(string $userId, array $options = []): object
  {
    try {
      $response = $this->spotifyApi->getUserPlaylists($userId, $options);
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      return $response;
    } catch (SpotifyWebAPIException $e) {
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      throw $e;
    }
  }

  public function createPlaylist(array $options): object
  {
    try {
      $response = $this->spotifyApi->createPlaylist($options);
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      return $response;
    } catch (SpotifyWebAPIException $e) {
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      throw $e;
    }
  }

  public function addPlaylistTracks(string $playlistId, array $trackUris): void
  {
    try {
      $this->spotifyApi->addPlaylistTracks($playlistId, $trackUris);
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
    } catch (SpotifyWebAPIException $e) {
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      throw $e;
    }
  }
  public function getTrack(string $trackId): object
  {
    try {
      $response = $this->spotifyApi->getTrack($trackId);
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      return $response;
    } catch (SpotifyWebAPIException $e) {
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      throw $e;
    }
  }
  public function replacePlaylistTracks(string $playlistId, array $trackUris): void
  {
    try {
      $this->spotifyApi->replacePlaylistTracks($playlistId, $trackUris);
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
    } catch (SpotifyWebAPIException $e) {
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      throw $e;
    }
  }

  public function me(): object
  {
    try {
      $response = $this->spotifyApi->me();
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      return $response;
    } catch (SpotifyWebAPIException $e) {
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      throw $e;
    }
  }


  public function getArtist(string $artistId): object
  {
    try {
      $response = $this->spotifyApi->getArtist($artistId);
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      return $response;
    } catch (SpotifyWebAPIException $e) {
      $this->lastResponseHeaders = $this->spotifyApi->getRequest()->getLastResponse()['headers'] ?? [];
      throw $e;
    }
  }

  public function getLastResponseHeaders(): array
  {
    return $this->lastResponseHeaders;
  }

  public function getSpotifyAPI(): SpotifyWebAPI
  {
    return $this->spotifyApi;
  }
}
