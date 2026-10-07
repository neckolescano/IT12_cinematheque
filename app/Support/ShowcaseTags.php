<?php

namespace App\Support;

use App\Models\Screening;

/**
 * Cinematheque notes shown on showcase posters, the spotlight and the schedule drawer
 * ("35mm Film Print", "Director Q&A", "Limited Run", "Curator's Pick", "Restored Classic"), and the
 * seat-availability level behind the showtime pills' dots. The notes come from what staff already
 * enter: the screening title, the film's curator's note and its release year.
 */
class ShowcaseTags
{
    /** Films at least this many years old are tagged "Restored Classic". */
    public const CLASSIC_AGE = 25;

    /** A screening is "selling fast" at or below this share of its seats (or 10 seats, if more). */
    public const SELLING_FAST = 0.25;

    /** Notes a staff-written screening title carries, e.g. "Himala (35mm) + Q&A with the director". */
    public static function forTitle(string $title): array
    {
        return array_keys(array_filter([
            '35mm Film Print' => preg_match('/\b(35|16|70)\s?mm\b/i', $title),
            'Director Q&A' => preg_match('/\bQ\s?&\s?A\b|talkback/i', $title),
            'Limited Run' => preg_match('/limited run|one night only/i', $title),
        ]));
    }

    /** Notes for a film ($film from PublicScreeningController::index), most specific first. */
    public static function for(object $film, int $limit = 2): array
    {
        $year = $film->movie?->release_year;

        return array_slice(array_values(array_unique([
            ...$film->shows->flatMap(fn ($s) => self::forTitle($s->event_title))->all(),
            ...($film->movie?->curator_note ? ["Curator's Pick"] : []),
            ...($year && $year <= now()->year - self::CLASSIC_AGE ? ['Restored Classic'] : []),
        ])), 0, $limit);
    }

    /** Seats left on a screening loaded withCount('heldSeats'). */
    public static function seatsLeft(Screening $screening): int
    {
        return max(0, $screening->total_seats - $screening->held_seats_count);
    }

    /** plenty / fast / full — the green, amber and red dots on showtime pills. */
    public static function seatLevel(Screening $screening): string
    {
        $left = self::seatsLeft($screening);

        return match (true) {
            $left === 0 => 'full',
            $left <= max(10, (int) floor($screening->total_seats * self::SELLING_FAST)) => 'fast',
            default => 'plenty',
        };
    }

    /** MTRCB rating → colour group for the poster badge. */
    public static function ratingGroup(string $rating): string
    {
        return match ($rating) {
            'G', 'PG' => 'general',
            'PG-13', 'R-13' => 'teen',
            default => 'adult',
        };
    }
}
