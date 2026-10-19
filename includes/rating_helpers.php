<?php
// Star ratings: the small "4.5 (12)" badge and the clickable star input.

/** Small badge such as "★ 4.5 (12)", or "No ratings yet". $avg may be null. */
function rating_badge($avg, int $count): string {
    if ($avg === null || $count < 1) {
        return '<span class="rating rating-none">No ratings yet</span>';
    }
    $score = number_format((float)$avg, 1);
    $label = 'Rated ' . $score . ' out of 5 from ' . $count . ($count === 1 ? ' rating' : ' ratings');
    return '<span class="rating" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
         . '<span class="rating-star">★</span><strong>' . $score . '</strong>'
         . '<span class="rating-count">(' . $count . ')</span></span>';
}

/**
 * Five clickable stars (radio buttons). $name is the form field, $id must be unique on the page,
 * $current is the star count already chosen (0 = none).
 */
function rating_star_input(string $name, string $id, int $current = 0): string {
    $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $id   = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $out  = '<div class="star-input" role="radiogroup">';
    // Written 5 to 1; the CSS reverses the row so the stars read 1 to 5 and fill up to the chosen one
    for ($i = 5; $i >= 1; $i--) {
        $out .= '<input type="radio" name="' . $name . '" id="' . $id . '-' . $i . '" value="' . $i . '"'
              . ($current === $i ? ' checked' : '') . '>'
              . '<label for="' . $id . '-' . $i . '" title="' . $i . ($i === 1 ? ' star' : ' stars') . '">'
              . '<span class="vh">' . $i . ($i === 1 ? ' star' : ' stars') . '</span>★</label>';
    }
    return $out . '</div>';
}
