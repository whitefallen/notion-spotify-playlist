<?php

namespace App\Service;

use Notion\Notion;
use Notion\Databases;
use Notion\Databases\Query;
use Notion\Databases\Query\Result;
use Notion\Pages\Page;

class NotionService
{
    private Notion $notion;
    private string $databaseId;

    public function __construct()
    {
        $this->notion = Notion::create($_ENV['NOTION_API_TOKEN']);
        $this->databaseId = $_ENV['NOTION_DATABASE_ID'];
    }

    /**
     * @throws \Exception
     */
    public function getArtistsFromNotion(): array
    {
        $artists = [];
        $database =  $this->notion->databases()->find($this->databaseId);
        $queryResult = $this->notion->databases()->queryAllPages(
            $database,
        );

        /** @var Result $result */
        foreach ($queryResult as $result) {
            $properties = $result->properties();
            $spotifyUri = $properties->get('Spotify URI');
            if ($spotifyUri) {
                $artists[] = $spotifyUri;
            }
        }

        return $artists;
    }
}
