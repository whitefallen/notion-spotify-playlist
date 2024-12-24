<?php

namespace App\Controller;

use App\Service\SpotifyService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SpotifyCallbackController
{
    #[Route('/callback', name: 'spotify_callback')]
    public function callback(Request $request): Response
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            return new Response("<h1>Error: $error</h1>", Response::HTTP_BAD_REQUEST);
        }

        if (!$code) {
            return new Response('<h1>No authorization code found</h1>', Response::HTTP_BAD_REQUEST);
        }

        try {
            $spotifyService = new SpotifyService();
            $spotifyService->requestAccessToken($code);

            return new Response('<h1>Authorization successful! Tokens have been saved.</h1>');
        } catch (\Exception $e) {
            return new Response('<h1>Failed to authorize with Spotify: ' . $e->getMessage() . '</h1>', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
