# Project Brief: Notion Spotify Playlist

## Project Overview

A Symfony PHP application that creates monthly Spotify playlists based on artists stored in Notion. The system fetches artists from a Notion database, finds their recent releases from the previous month using Spotify's API, and creates/updates a monthly playlist.

## Core Requirements

- Fetch artist list from Notion database
- Query Spotify API for recent releases (singles and albums) from the previous month
- Filter out unwanted content (instrumentals, live versions, remixes, etc.)
- Create or update a Spotify playlist with the filtered tracks
- Run automatically via scheduled CRON job

## Key Features

- Notion integration for artist management
- Spotify API integration for track discovery
- Intelligent filtering of unwanted content
- Monthly playlist generation
- Automated execution via Docker/CRON

## Technical Stack

- PHP 8.1 with Symfony framework
- Docker containerization
- Spotify Web API PHP library
- Notion API integration
- CRON scheduling for automation

## Current Status

The application is functional but has a critical rate limiting issue that causes the script to stop after encountering the second API rate limit error.
