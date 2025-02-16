<?php

namespace App\Command;

use App\Service\NotionService;
use App\Service\SpotifyService;
use SpotifyWebAPI\SpotifyWebAPI;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'spotify:update-playlist',
    description: 'Updates the Spotify playlist for the current month using artists from Notion'
)]
class UpdatePlaylistCommand extends Command
{
    private SpotifyService $spotifyService;
    private NotionService $notionService;
    private static array $blacklistedWords = ['instrumental', 'acoustic', 'live', 'karaoke', 'remix', 'cover', 'remaster', 'edition', 'version', 'session', 'demo', 'mix', 'track', 'original', 'edit', 'extended'];

    public function __construct(SpotifyService $spotifyService, NotionService $notionService)
    {
        parent::__construct();
        $this->spotifyService = $spotifyService;
        $this->notionService = $notionService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Updates the Spotify playlist for the current month using artists from Notion');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Step 1: Get artists from Notion
            $output->writeln('Fetching artists from Notion...');
            $artistUris = $this->notionService->getArtistsFromNotion();

            if (empty($artistUris)) {
                $output->writeln('<error>No artists found in Notion.</error>');
                return Command::FAILURE;
            }

            // Step 2: Set up Spotify API client
            $output->writeln('Initializing Spotify API...');
            $spotifyApi = $this->spotifyService->getSpotifyAPI();

            // Step 3: Find recent tracks for the artists
            $output->writeln('Fetching recent tracks for artists...');
            $singleTrackUris = $this->getRecentArtistItems($spotifyApi, $artistUris, 'single', $output);
            $albumTrackUris = $this->getRecentArtistItems($spotifyApi, $artistUris, 'album', $output);
            $mergedTrackUris = array_merge($singleTrackUris, $albumTrackUris);
            $trackUris = $this->filterUnwantedSongs($spotifyApi, $this->filterDuplicateTracks($spotifyApi, $mergedTrackUris));

            if (empty($trackUris)) {
                $output->writeln('<error>No recent tracks found for the artists.</error>');
                return Command::FAILURE;
            }

            // Step 4: Create or update playlist
            $playlistName = (new \DateTime('first day of last month'))->format('F Y');
            $output->writeln("Creating or updating playlist: $playlistName...");
            $playlistId = $this->spotifyService->createOrUpdatePlaylist($playlistName, $trackUris);

            $output->writeln("<info>Playlist updated successfully! Playlist ID: $playlistId</info>");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * Fetches recent tracks for a list of artists from Spotify, filtering out duplicate songs by the same artist.
     *
     * @param SpotifyWebAPI $spotifyApi The Spotify API client.
     * @param array $artistUris An array of artist URIs.
     * @param string $searchType The type of search to perform ('single' or 'album').
     * @return array An array of unique track URIs.
     */
    private function getRecentArtistItems(SpotifyWebAPI $spotifyApi, array $artistUris, string $searchType, OutputInterface $output): array
    {
        $recentTracks = [];

        foreach ($artistUris as $artistUri) {
            try {
                $artistId = str_replace('spotify:artist:', '', $artistUri->toString());
                $items = $spotifyApi->getArtistAlbums($artistId, ['include_groups' => $searchType, 'limit' => 5]);
                $output->writeln("Fetching recent $searchType items for artist: " . $items->items[0]->artists[0]->name);
                $firstDayOfLastMonth = (new \DateTime('first day of last month'))->setTime(0, 0);
                $lastDayOfLastMonth = (new \DateTime('last day of last month'))->setTime(23, 59, 59);

                foreach ($items->items as $album) {

                    $releaseDate = new \DateTime($album->release_date);

                    if ($releaseDate >= $firstDayOfLastMonth && $releaseDate <= $lastDayOfLastMonth) {
                        $tracks = $spotifyApi->getAlbumTracks($album->id, ['limit' => 50]);

                        foreach ($tracks->items as $track) {
                            $recentTracks[] = $track->uri;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log error for a specific artist but continue with others
                continue;
            }
            sleep(2);
        }

        return array_unique($recentTracks); // Avoid duplicate track URIs
    }

    private function filterDuplicateTracks(SpotifyWebAPI $spotifyApi, array $mergedTrackUris) : array
    {
        $trackUris = [];
        $trackCheck = [];

        foreach ($mergedTrackUris as $trackUri) {
            $track = $spotifyApi->getTrack($trackUri);
            $trackName = $track->name;
            $trackArtistId = $track->artists[0]->id;

            if (!isset($trackCheck[$trackArtistId][$trackName])) {
                $trackUris[] = $trackUri;
                $trackCheck[$trackArtistId][$trackName] = true;
            }
        }
        return $trackUris;
    }

    /**
     * Filter out songs that have
     * "instrumental", "acoustic", "live", "karaoke", "remix", "cover" ," remaster",
     * "edition", "version", "session", "demo", "mix", "track", "original", "edit", "extended"
     * in the name and the album name
     * @param SpotifyWebAPI $spotifyApi
     * @param array $trackUris
     * @return array
     */
    private function filterUnwantedSongs(SpotifyWebAPI $spotifyApi, array $trackUris) : array
    {
        // Filter the array based on the phpdoc description
        return array_filter($trackUris, static function($trackUri) use ($spotifyApi) {
            $track = $spotifyApi->getTrack($trackUri);
            $trackName = strtolower($track->name);
            $albumName = strtolower($track->album->name);

            foreach (UpdatePlaylistCommand::$blacklistedWords as $word) {
                if (str_contains($trackName, $word) || str_contains($albumName, $word)) {
                    return false;
                }
            }
            return true;
        });
    }
}
