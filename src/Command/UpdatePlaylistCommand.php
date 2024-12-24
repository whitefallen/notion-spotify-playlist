<?php

namespace App\Command;

use App\Service\NotionService;
use App\Service\SpotifyService;
use SpotifyWebAPI\SpotifyWebAPI;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdatePlaylistCommand extends Command
{
    protected static $defaultName = 'spotify:update-playlist';
    private SpotifyService $spotifyService;
    private NotionService $notionService;

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
            $singleTrackUris = $this->getRecentArtistItems($spotifyApi, $artistUris, 'single');
            $albumTrackUris = $this->getRecentArtistItems($spotifyApi, $artistUris, 'album');
            $mergedTrackUris = array_merge($singleTrackUris, $albumTrackUris);

            foreach ($mergedTrackUris as $trackUri) {
                $track = $spotifyApi->getTrack($trackUri);
                $trackName = $track->name;
                $trackArtistId = $track->artists[0]->id;

                if (!isset($trackCheck[$trackArtistId][$trackName])) {
                    $trackUris[] = $trackUri;
                    $trackCheck[$trackArtistId][$trackName] = true;
                }
            }

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
    private function getRecentArtistItems(SpotifyWebAPI $spotifyApi, array $artistUris, string $searchType): array
    {
        $recentTracks = [];

        foreach ($artistUris as $artistUri) {
            try {
                $artistId = str_replace('spotify:artist:', '', $artistUri->toString());
                $items = $spotifyApi->getArtistAlbums($artistId, ['include_groups' => $searchType, 'limit' => 5]);

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
            sleep(10);
        }

        return array_unique($recentTracks); // Avoid duplicate track URIs
    }
}
