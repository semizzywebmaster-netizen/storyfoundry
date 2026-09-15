<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/**
 * MOCK AI providers (local, deterministic, no network, no keys).
 * These exist so the platform is usable before you add API keys, and so
 * demos/tests never spend money. Status is ALWAYS reported as MOCK.
 * Capabilities: text, image, voice, music, sfx.
 */
function mockai_rng($seed) {
    $h = 2166136261;
    foreach (str_split((string) $seed) as $ch) { $h ^= ord($ch); $h = ($h * 16777619) & 0xFFFFFFFF; }
    mt_srand($h);
    return function () { return mt_rand() / mt_getrandmax(); };
}
function mockai_pick($arr, $r) {
    if (!is_array($arr) || !$arr) return '';
    $arr = array_values($arr);
    return $arr[(int) floor($r() * count($arr)) % count($arr)];
}
function mockai_ri($a, $b, $r) { return $a + (int) floor($r() * ($b - $a + 1)); }
function mockai_fill($tpl, $ctx) {
    return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($ctx) {
        $k = $m[1]; return isset($ctx[$k]) ? $ctx[$k] : $m[0];
    }, $tpl);
}

/* ------------------------------------------------------------------ TEXT */
function mockai_text($kind, $ctx, $n = 4) {
    /* defensive defaults so no caller can crash the engine */
    if (empty($ctx['names']) || !is_array($ctx['names'])) { $ctx['names'] = array('Ada', 'Tunde', 'Ngozi', 'Bashir'); }
    if (empty($ctx['places']) || !is_array($ctx['places'])) { $ctx['places'] = array(isset($ctx['place']) && $ctx['place'] ? $ctx['place'] : 'the old market'); }
    if (empty($ctx['name'])) { $ctx['name'] = $ctx['names'][0]; }
    if (empty($ctx['name2'])) { $ctx['name2'] = isset($ctx['names'][1]) ? $ctx['names'][1] : $ctx['names'][0]; }
    if (empty($ctx['place'])) { $ctx['place'] = $ctx['places'][0]; }
    if (empty($ctx['title'])) { $ctx['title'] = 'Untitled'; }
    if (empty($ctx['characters']) || !is_array($ctx['characters'])) { $ctx['characters'] = array($ctx['name'], $ctx['name2']); }
    $r = mockai_rng($kind . '|' . json_encode($ctx) . '|' . (isset($ctx['n']) ? $ctx['n'] : 0));
    $openers = array(
        'Nobody in {place} locked their doors until the night {name} came back.',
        'The morning the letter arrived, {name} was already three weeks late for her own life.',
        'There is a particular kind of silence that settles over {place} before something breaks.',
        '{name} had learned to count the seconds between the generator cutting out and the street going quiet.',
        'Everybody said {place} was changing. {name} was the only one keeping score.',
        'It began, as most things do there, with a debt nobody had written down.',
        'The rain came early that year, and with it the thing {name} had spent a decade not talking about.',
    );
    $beats = array(
        'She tried the old routine — {food}, the radio, the walk past the {flora} — but the day refused to behave.',
        'A message arrived with no name attached: two words that made the room feel smaller.',
        'By afternoon the argument had stopped being about the money.',
        'He drove across {place} with the windows down, rehearsing an apology he would never deliver.',
        'The decision cost her something she could not name, and she made it anyway.',
        'What neither of them knew was that the paperwork had already been signed.',
        'Somewhere between the second call and the third, the plan quietly changed shape.',
        'It should have been the easiest lie of his life. It wasn\'t.',
        'The {flora} outside the window had not moved, but the light had.',
    );
    $turns = array(
        'Then the power went out across {place}, and everyone heard the same thing at once.',
        'The knock at the door came three hours too early. Or three years too late.',
        'No one had told {name} that the meeting had already happened.',
        'The truth arrived in the least dramatic way possible: a forwarded screenshot.',
        'What followed was not a chase. It was an accounting.',
    );
    $closers = array(
        'In the end {place} went back to being itself, the way water does. {name} did not.',
        'She kept the letter. Not for the words — for the handwriting.',
        'By morning it was already being told differently, and that version is the one that survived.',
        'He would describe it later as the day he stopped waiting for permission.',
        'The last thing on the recording is not a voice. It is a door, and then nothing.',
    );

    if ($kind === 'story') {
        $chapters = array();
        $nch = isset($ctx['chapters']) ? (int) $ctx['chapters'] : 3;
        for ($i = 0; $i < $nch; $i++) {
            $parts = array(mockai_fill(mockai_pick($openers, $r), $ctx));
            for ($b = 0; $b < 3; $b++) { $parts[] = mockai_fill(mockai_pick($beats, $r), $ctx); }
            $parts[] = mockai_fill(mockai_pick($turns, $r), $ctx);
            $parts[] = mockai_fill(mockai_pick($closers, $r), $ctx);
            $text = implode(' ', $parts);
            $chapters[] = array(
                'title' => 'Chapter ' . ($i + 1),
                'text' => $text,
                'words' => str_word_count($text),
            );
        }
        $words = 0; foreach ($chapters as $c) { $words += $c['words']; }
        return array('title' => isset($ctx['title']) ? $ctx['title'] : 'Untitled', 'chapters' => $chapters, 'words' => $words,
            'logline' => mockai_fill(mockai_pick($openers, $r), $ctx));
    }
    if ($kind === 'ideas') {
        $out = array();
        $twists = array('The letter was never sent by the person who signed it.', 'The witness is the one who arranged it.',
            'The money was returned years ago.', 'The stranger is not a stranger.', 'The recording has a second voice underneath.');
        $conflicts = array('A debt that predates the friendship.', 'Two versions of the same night.',
            'An inheritance nobody wants to name.', 'A promotion that requires a betrayal.', 'A secret kept for the wrong reason.');
        for ($i = 0; $i < $n; $i++) {
            $out[] = array(
                'title' => trim(mockai_pick(array('The Last', 'The First', 'The Long', 'The Quiet', 'The Broken'), $r) . ' ' .
                                mockai_pick(array('Season', 'Debt', 'Letter', 'Harvest', 'Signal', 'Door', 'Promise'), $r)),
                'logline' => mockai_fill(mockai_pick($openers, $r), $ctx),
                'conflict' => mockai_pick($conflicts, $r),
                'twist' => mockai_pick($twists, $r),
                'ending' => mockai_pick(array('Ambiguous — the door closes, we stay outside.',
                    'Restorative — the debt is named, not settled.',
                    'Costly — she wins and loses the same room.',
                    'Open — a new name on the paperwork.'), $r),
            );
        }
        return $out;
    }
    if ($kind === 'characters') {
        $out = array();
        $roles = array(array('Protagonist', 'Carries the want'), array('Antagonist', 'Blocks the want'),
            array('Confidant', 'Holds the truth'), array('Wildcard', 'Changes the math'), array('Mentor', 'Names the cost'));
        for ($i = 0; $i < $n; $i++) {
            $nm = mockai_pick($ctx['names'], $r);
            $out[] = array(
                'id' => 'chr_' . substr(md5($nm . $i), 0, 8), 'name' => $nm,
                'role' => $roles[$i % count($roles)][0], 'role_note' => $roles[$i % count($roles)][1],
                'age' => mockai_ri(17, 68, $r),
                'personality' => mockai_pick(array('Careful, quietly stubborn, allergic to being owed',
                    'Warm, articulate, hides panic behind hospitality', 'Restless, funny, avoids stillness',
                    'Precise, private, keeps three ledgers'), $r),
                'background' => 'Grew up in ' . mockai_pick($ctx['places'], $r) . '; ' . mockai_pick(array('left at nineteen', 'never left', 'came back last year'), $r) . '.',
                'motivation' => mockai_pick(array('To finish something their family started', 'To be owed nothing by anyone',
                    'To keep a promise made in a bad year', 'To be believed, just once'), $r),
                'fear' => mockai_pick(array('Being found out by the one person who raised them',
                    'Turning into the parent they left', 'That the debt was the only thing holding them together'), $r),
                'arc' => mockai_pick(array('From avoidance to accountability', 'From control to surrender',
                    'From silence to testimony', 'From revenge to restraint'), $r),
                'appearance' => mockai_pick(array('Slim, close-cropped hair, a scar at the left brow',
                    'Broad-shouldered, short locs, reads older than their age', 'Tall, angular, always in the same grey coat'), $r),
                'clothing' => mockai_pick(array('Indigo wrapper and a plain white tee', 'Tailored two-piece, unbuttoned at the collar',
                    'Faded denim, market sandals, a beaded wristband'), $r),
                'voice' => mockai_pick(array('Warm alto, unhurried', 'Gravel tenor, clipped endings',
                    'Light, quick, laughs mid-sentence', 'Low and level, says the most in the fewest words'), $r),
                'locked' => false, 'relationships' => array(),
            );
        }
        return $out;
    }
    if ($kind === 'scenes') {
        $out = array();
        $titles = array('The Compound', 'The Office', 'Rooftop, Later', 'The Market Run', 'Night Drive',
            'The Hospital Room', 'The Courtyard', 'The Bus Stop', 'The Riverbank', 'The Kitchen, 4am');
        for ($i = 0; $i < $n; $i++) {
            $out[] = array(
                'id' => 'sc_' . substr(md5($i . '|' . microtime(true)), 0, 8), 'n' => $i + 1,
                'title' => $titles[$i % count($titles)],
                'location' => mockai_pick($ctx['places'], $r),
                'time' => mockai_pick(array('Dawn', 'Morning', 'Midday', 'Afternoon', 'Golden hour', 'Dusk', 'Night'), $r),
                'weather' => mockai_pick(array('Clear', 'Overcast', 'Light rain', 'Heavy rain', 'Harmattan haze', 'Fog'), $r),
                'mood' => mockai_pick(array('Tense', 'Hopeful', 'Melancholic', 'Joyful', 'Foreboding', 'Tender', 'Serene'), $r),
                'characters' => array_slice($ctx['names'], 0, mockai_ri(1, min(3, count($ctx['names'])), $r)),
                'action' => mockai_pick(array('A deal is declined without a word being said.', 'Someone counts money badly on purpose.',
                    'Two people talk past each other in a moving car.', 'A photograph is turned face down.',
                    'A door is opened by someone who was not expected.'), $r),
                'props' => mockai_pick(array('a sealed envelope', 'a rusted key', 'a cassette tape', 'a ledger', "a nurse's watch"), $r),
                'direction' => mockai_pick(array('Let the silence run past comfortable.', 'Keep the camera low — the room should feel like it is leaning in.',
                    'Two-shot held until one of them breaks.', 'Handheld, no score, let the street do the work.'), $r),
                'continuity' => 'Continues from the previous scene. Costume and weather must match.',
                'shots' => array(),
            );
        }
        return $out;
    }
    if ($kind === 'shots') {
        $out = array(); $types = array('Extreme wide', 'Wide / establishing', 'Medium', 'Medium close-up', 'Close-up', 'Two-shot', 'Insert', 'POV');
        $angles = array('Eye level', 'Low angle', 'High angle', 'Dutch angle', 'Over the shoulder', 'Bird\'s eye');
        $moves = array('Static', 'Pan L>R', 'Pan R>L', 'Tilt up', 'Dolly in', 'Dolly out', 'Tracking', 'Handheld', 'Zoom in');
        $lenses = array('18mm wide', '24mm', '35mm', '50mm', '85mm portrait', 'Anamorphic 40mm');
        for ($i = 0; $i < $n; $i++) {
            $out[] = array(
                'id' => 'sh_' . substr(md5((isset($ctx['scene_id']) ? $ctx['scene_id'] : 's1') . $i . microtime(true)), 0, 8), 'n' => $i + 1,
                'type' => mockai_pick($types, $r), 'angle' => mockai_pick($angles, $r), 'move' => mockai_pick($moves, $r),
                'lens' => mockai_pick($lenses, $r), 'subject' => isset($ctx['characters'][0]) ? $ctx['characters'][0] : 'the room',
                'action' => mockai_pick(array('Enters and stops short', 'Reacts without turning', 'Sets something down deliberately',
                    'Holds on the face two beats too long'), $r),
                'duration' => mockai_ri(2, 7, $r), 'dialogue' => $i === 1 ? 'You said you would handle it.' : '',
                'audio' => mockai_pick(array('Room tone + distant traffic', 'Score enters under the line', 'SFX: rain on zinc'), $r),
                'lighting' => mockai_pick(array('Single window, hard shaft', 'Practical lamp, low key', 'Overcast, soft wrap'), $r),
                'note' => 'Protect the left edge for subtitles.',
            );
        }
        return $out;
    }
    /* generic paragraph / continuation */
    $p = array(mockai_fill(mockai_pick($openers, $r), $ctx));
    for ($b = 0; $b < 2; $b++) { $p[] = mockai_fill(mockai_pick($beats, $r), $ctx); }
    $p[] = mockai_fill(mockai_pick($closers, $r), $ctx);
    return implode(' ', $p);
}
function mockai_rewrite($text, $style, $ctx) {
    $r = mockai_rng(md5($text) . $style);
    $head = array(
        'Cinematic' => 'The camera finds the room before it finds the people in it. ',
        'Emotional' => 'What neither of them said was louder than the sentence itself. ',
        'Humorous'  => 'It went about as well as anything in ' . (isset($ctx['place']) ? $ctx['place'] : 'town') . ' goes after 4pm. ',
        'Dramatic'  => 'This was the last ordinary moment, and everybody in it knew. ',
        'Concise'   => '', 'Expanded' => 'There is a version of this that begins earlier, with the generator cutting out and nobody reacting. ',
        'Elevated'  => 'It is a curious thing, the arithmetic of a household in debt. ', 'Plain' => '',
    );
    $sents = preg_split('/(?<=[.!?])\s+/', trim($text));
    $sents = array_values(array_filter($sents));
    if (!$sents) return $text;
    $keep = $style === 'Concise' ? array_slice($sents, 0, max(1, (int) ceil(count($sents) * 0.6))) : $sents;
    $out = (isset($head[$style]) ? $head[$style] : '') . implode(' ', $keep);
    if ($style === 'Expanded') { $out .= ' ' . mockai_text('paragraph', $ctx); }
    return $out;
}

/* ----------------------------------------------------------------- IMAGE */
/** Accepts either mockai_frame_svg($options) or mockai_frame_svg($seed, $options). */
function mockai_frame_svg($seedOrOpts, $extra = array()) {
    if (is_array($seedOrOpts)) { $o = $seedOrOpts; }
    else { $o = array_merge(array('seed' => (string) $seedOrOpts), is_array($extra) ? $extra : array()); }

    $o = array_merge(array('seed' => 'sf', 'style' => 'cinematic', 'w' => 640, 'h' => 360, 'subject' => 'scene',
        'figures' => 1, 'time' => 'day', 'text' => ''), $o);
    $r = mockai_rng($o['seed'] . '|' . $o['style'] . '|' . $o['subject']);
    $P = array(
        'cinematic' => array('#0b2438', '#3f5f73', '#e08a45', '#ffd9a0', '#16303f', '#0d1f2b', '#080f16', '#7fa8c9', '#05090d', '#ffb020'),
        'realistic' => array('#5b8fb0', '#a8c4d4', '#dfe6e8', '#fff3d6', '#6d8a99', '#3f5561', '#2c3a33', '#cdd8dc', '#1f2a2e', '#e8c07d'),
        'anime'     => array('#8fb8ff', '#ffd6e8', '#ffe9c9', '#fff6d8', '#a5b8e8', '#5c6ba8', '#7fae72', '#ffe3ef', '#3b3f66', '#ff8fb1'),
        'cartoon'   => array('#39a9f0', '#8fd8ff', '#d9f4ff', '#ffe066', '#5fb3e6', '#2f8fcf', '#4caf50', '#ffffff', '#222831', '#ff7043'),
        'threed'    => array('#cfe6f5', '#eaf3f9', '#f6fbff', '#ffffff', '#b9cfe0', '#8fa9bd', '#dfe6ea', '#ffffff', '#6b7a88', '#ffb020'),
        'documentary'=>array('#7d8a93', '#a9b3b8', '#c8cecf', '#e6e6e0', '#64707a', '#414b53', '#333b3f', '#b6bcc0', '#1c2124', '#d1d5d9'),
        'film'      => array('#2b1d14', '#7a4a28', '#d79a55', '#ffdca8', '#3a2418', '#241610', '#160d08', '#c98f52', '#0d0705', '#e8a75c'),
        'comic'     => array('#1b2a4a', '#3f5f9e', '#7fa4e0', '#ffd93d', '#24365c', '#141f38', '#0d1526', '#5f86c9', '#080d18', '#ff3b3b'),
        'darkcinematic'=>array('#05070d', '#0d1626', '#1d2b40', '#5f7fa8', '#0a1220', '#060b14', '#03060b', '#2a405c', '#010305', '#5aa9ff'),
        'fantasy'   => array('#1a0b2e', '#5b2a86', '#c9728f', '#ffe0a3', '#2c1250', '#1a0930', '#120620', '#8b5cc7', '#080310', '#ffd166'),
        'historical'=> array('#6b5638', '#b39668', '#e0cba4', '#fff0c8', '#7d6642', '#513f28', '#3a2c1a', '#d8c39a', '#241a0d', '#c9a227'),
        'illustration'=>array('#f4d9c0', '#f9ead9', '#fff6ec', '#ffcf8f', '#d9a882', '#a8763f', '#8a5a34', '#fff1e0', '#4a2f1c', '#e07a5f'),
        'noir'      => array('#101215', '#2a2e34', '#4a5058', '#cfd4d9', '#1a1d22', '#0e1013', '#08090b', '#5a6068', '#000000', '#e8eaee'),
    );
    $p = isset($P[$o['style']]) ? $P[$o['style']] : $P['cinematic'];
    list($s0, $s1, $s2, $sun, $far, $mid, $ground, $fog, $fig, $acc) = $p;
    if ($o['time'] === 'night') { $s0 = '#05070f'; $s1 = '#0b1322'; $s2 = '#1c2b45'; $sun = '#8fa8c9'; }
    if ($o['time'] === 'dusk') { $s0 = '#1a1030'; $s1 = '#5c2a5a'; $s2 = '#e0705a'; }
    if ($o['time'] === 'golden') { $s0 = '#2d1b33'; $s1 = '#a8543a'; $s2 = '#ffc16b'; }

    $w = (int) $o['w']; $h = (int) $o['h'];
    $horizon = $o['subject'] === 'character' ? $h * 0.78 : $h * (0.56 + $r() * 0.14);
    $gid = substr(md5($o['seed'] . $o['subject']), 0, 6);
    $sunX = (int) (($r() * 0.76 + 0.12) * $w); $sunY = (int) ($horizon * (0.22 + $r() * 0.5));
    $sunR = mockai_ri(10, 22, $r);

    $s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '">';
    $s .= '<defs><linearGradient id="sk' . $gid . '" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%" stop-color="' . $s0 . '"/><stop offset="55%" stop-color="' . $s1 . '"/><stop offset="100%" stop-color="' . $s2 . '"/>'
        . '</linearGradient><radialGradient id="su' . $gid . '"><stop offset="0%" stop-color="' . $sun . '" stop-opacity=".95"/>'
        . '<stop offset="45%" stop-color="' . $sun . '" stop-opacity=".3"/><stop offset="100%" stop-color="' . $sun . '" stop-opacity="0"/></radialGradient>'
        . '<linearGradient id="vg' . $gid . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#000" stop-opacity=".35"/>'
        . '<stop offset="45%" stop-color="#000" stop-opacity="0"/><stop offset="100%" stop-color="#000" stop-opacity=".42"/></linearGradient>'
        . '<filter id="gr' . $gid . '"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" seed="' . mockai_ri(1, 99, $r) . '"/>'
        . '<feColorMatrix type="saturate" values="0"/></filter></defs>';
    $s .= '<rect width="' . $w . '" height="' . $h . '" fill="url(#sk' . $gid . ')"/>';
    $s .= '<circle cx="' . $sunX . '" cy="' . $sunY . '" r="' . ($sunR * 3) . '" fill="url(#su' . $gid . ')"/>';
    $s .= '<circle cx="' . $sunX . '" cy="' . $sunY . '" r="' . $sunR . '" fill="' . $sun . '" opacity=".9"/>';

    /* clouds */
    for ($i = 0; $i < 3; $i++) {
        $cx = (int) ($r() * $w); $cy = (int) ($horizon * (0.15 + $r() * 0.45)); $cw = mockai_ri(50, 150, $r);
        $s .= '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="' . $cw . '" ry="' . (int) ($cw * 0.16) . '" fill="' . $fog . '" opacity="' . number_format(0.10 + $r() * 0.14, 2) . '"/>';
    }
    if ($o['subject'] === 'character') {
        $s .= '<rect width="' . $w . '" height="' . $h . '" fill="' . $far . '" opacity=".55"/>';
        $s .= '<ellipse cx="' . ($w / 2) . '" cy="' . ($h * 0.72) . '" rx="' . ($w * 0.34) . '" ry="' . ($h * 0.1) . '" fill="' . $mid . '" opacity=".5"/>';
        $s .= mockai_figure($w / 2, $h * 0.26, $h * 0.62, $fig);
    } else {
        $s .= mockai_ridge($w, $horizon, $h * 0.16, $far, 7, $r);
        $s .= mockai_ridge($w, $horizon + $h * 0.05, $h * 0.11, $mid, 6, $r);
        $s .= '<rect y="' . (int) ($horizon + $h * 0.10) . '" width="' . $w . '" height="' . (int) $h . '" fill="' . $ground . '"/>';
        for ($i = 0; $i < (int) max(3, $w / 70); $i++) {
            $x = (int) ($r() * $w); $th = $h * 0.16 * (mockai_ri(6, 12, $r) / 10);
            $s .= '<path d="M ' . $x . ' ' . (int) ($horizon + $h * 0.12) . ' L ' . (int) ($x - $th * 0.16) . ' ' . (int) ($horizon + $h * 0.12)
                . ' L ' . $x . ' ' . (int) ($horizon + $h * 0.12 - $th) . ' L ' . (int) ($x + $th * 0.16) . ' ' . (int) ($horizon + $h * 0.12) . ' Z" fill="' . $mid . '"/>';
        }
        $s .= '<rect y="' . (int) ($horizon - $h * 0.05) . '" width="' . $w . '" height="' . (int) ($h * 0.13) . '" fill="' . $fog . '" opacity=".13"/>';
        $n = max(1, (int) $o['figures']);
        for ($i = 0; $i < min(3, $n); $i++) {
            $fh = $h * (0.30 - $i * 0.045);
            $fx = $w * (0.22 + $i * 0.26 + $r() * 0.08);
            $s .= mockai_figure($fx, $horizon + $h * 0.10 - $fh * 0.06, $fh, $fig);
        }
    }
    $s .= '<rect width="' . $w . '" height="' . $h . '" fill="url(#vg' . $gid . ')"/>';
    if (in_array($o['style'], array('film', 'noir', 'darkcinematic', 'cinematic'), true)) {
        $lb = (int) ($h * 0.055);
        $s .= '<rect width="' . $w . '" height="' . $lb . '" fill="#000"/><rect y="' . ($h - $lb) . '" width="' . $w . '" height="' . $lb . '" fill="#000"/>';
    }
    if ($o['subject'] === 'thumb' && $o['text']) {
        $t = strtoupper(substr($o['text'], 0, 26));
        $s .= '<text x="' . ($w / 2) . '" y="' . (int) ($h * 0.90) . '" font-family="Arial Black,Impact,sans-serif" font-size="' . (int) ($h * 0.135)
            . '" fill="#fff" stroke="#000" stroke-width="' . (int) ($h * 0.016) . '" paint-order="stroke" text-anchor="middle">' . e($t) . '</text>';
    }
    $s .= '</svg>';
    return $s;
}
function mockai_figure($x, $y, $h, $fill) {
    $u = $h / 8; $head = $u * 1.05;
    return '<g fill="' . $fill . '" transform="translate(' . round($x, 1) . ',' . round($y, 1) . ')">'
        . '<circle cx="0" cy="' . round($u * 0.55, 1) . '" r="' . round($head, 1) . '"/>'
        . '<path d="M ' . round(-$u * 1.15, 1) . ' ' . round($h, 1) . ' L ' . round(-$u * 0.95, 1) . ' ' . round($u * 1.9, 1)
        . ' Q 0 ' . round($u * 1.35, 1) . ' ' . round($u * 0.95, 1) . ' ' . round($u * 1.9, 1)
        . ' L ' . round($u * 1.15, 1) . ' ' . round($h, 1) . ' Z"/>'
        . '<path d="M ' . round(-$u * 0.95, 1) . ' ' . round($u * 2.2, 1) . ' Q ' . round(-$u * 2.1, 1) . ' ' . round($u * 3.1, 1) . ' ' . round(-$u * 1.5, 1) . ' ' . round($u * 4.6, 1) . '" stroke="' . $fill . '" stroke-width="' . round($u * 0.42, 1) . '" fill="none"/>'
        . '<path d="M ' . round($u * 0.95, 1) . ' ' . round($u * 2.2, 1) . ' Q ' . round($u * 2.1, 1) . ' ' . round($u * 3.0, 1) . ' ' . round($u * 1.55, 1) . ' ' . round($u * 4.4, 1) . '" stroke="' . $fill . '" stroke-width="' . round($u * 0.42, 1) . '" fill="none"/>'
        . '</g>';
}
function mockai_ridge($w, $baseY, $amp, $fill, $steps, $r) {
    $d = 'M 0 999 L 0 ' . round($baseY, 1);
    $seg = $w / $steps;
    for ($i = 0; $i <= $steps; $i++) {
        $x = $i * $seg; $y = $baseY - (sin($i * 0.9 + $r() * 0.7) * $amp * 0.5 + $r() * $amp * 0.6);
        $d .= ' L ' . round($x, 1) . ' ' . round($y, 1);
    }
    $d .= ' L ' . $w . ' 999 Z';
    return '<path d="' . $d . '" fill="' . $fill . '"/>';
}
/** Write a generated frame to storage; returns array(path,url,status) */
function mockai_image($prompt, $o = array()) {
    $svg = mockai_frame_svg(array_merge(array('seed' => md5($prompt . (isset($o['seed']) ? $o['seed'] : ''))), $o));
    $rel = 'uploads/assets/frame-' . date('Ymd') . '-' . sf_token(8) . '.svg';
    $p = SF_ROOT . '/' . $rel;
    if (!is_dir(dirname($p))) @mkdir(dirname($p), 0755, true);
    file_put_contents($p, $svg);
    return array('ok' => true, 'path' => $rel, 'url' => storage_url($rel), 'mime' => 'image/svg+xml', 'provider' => 'mock.framesynth', 'status' => 'MOCK');
}

/* ----------------------------------------------------------------- AUDIO */
/** PCM 16-bit mono WAV writer (pure PHP — no ffmpeg needed). */
function wav_write($path, $samples, $rate = 22050) {
    $n = count($samples);
    $data = '';
    for ($i = 0; $i < $n; $i++) {
        $v = max(-1, min(1, $samples[$i]));
        $data .= pack('v', (int) round($v * 32767));
    }
    $hdr = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVEfmt ' . pack('V', 16) . pack('vv', 1, 1)
        . pack('V', $rate) . pack('V', $rate * 2) . pack('v', 2) . pack('v', 16) . 'data' . pack('V', strlen($data));
    $dir = dirname($path); if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return file_put_contents($path, $hdr . $data) !== false;
}
function mockai_voice($text, $voice = array(), $seconds = 0) {
    $seed = isset($voice['id']) ? $voice['id'] : 'v1';
    $r = mockai_rng($seed . '|' . $text);
    $rate = 22050;
    $words = max(1, str_word_count($text));
    $dur = $seconds > 0 ? $seconds : min(30, max(2, $words * 0.42));
    $n = (int) ($rate * $dur);
    $base = (isset($voice['gender']) && $voice['gender'] === 'Masculine') ? 128 : 210;
    $out = array_fill(0, $n, 0.0);
    $t = 0.0;
    while ($t < $dur) {
        $slen = 0.12 + $r() * 0.12;
        $f = $base * (1 + ($r() - 0.5) * 0.2) * ($r() < 0.3 ? 1.16 : 1);
        $start = (int) ($t * $rate); $len = (int) ($slen * $rate);
        for ($i = 0; $i < $len && ($start + $i) < $n; $i++) {
            $env = sin(M_PI * ($i / $len));
            $x = $i / $rate;
            $s = sin(2 * M_PI * $f * $x) * 0.6 + sin(2 * M_PI * $f * 2 * $x) * 0.25 + sin(2 * M_PI * $f * 3 * $x) * 0.12;
            $out[$start + $i] += $s * $env * 0.28;
        }
        $t += $slen + 0.05 + $r() * 0.05;
    }
    $rel = 'uploads/assets/voice-' . date('Ymd') . '-' . sf_token(8) . '.wav';
    $p = SF_ROOT . '/' . $rel;
    if (!wav_write($p, $out, $rate)) return array('ok' => false, 'error' => 'Could not write audio');
    return array('ok' => true, 'path' => $rel, 'url' => storage_url($rel), 'duration' => round($dur, 2), 'provider' => 'mock.voicesynth', 'status' => 'MOCK');
}
function mockai_music($mood = 'Cinematic', $seconds = 12) {
    $r = mockai_rng($mood . $seconds);
    $rate = 22050; $n = (int) ($rate * $seconds);
    $scales = array('Am' => array(220, 261.63, 293.66, 329.63, 392, 440), 'C' => array(261.63, 293.66, 329.63, 392, 440, 523.25));
    $scale = $scales['Am']; $out = array_fill(0, $n, 0.0);
    $t = 0.0;
    while ($t < $seconds) {
        $f = $scale[(int) floor($r() * count($scale))];
        $len = (int) ($rate * (0.9 + $r() * 0.7)); $start = (int) ($t * $rate);
        for ($i = 0; $i < $len && ($start + $i) < $n; $i++) {
            $env = sin(M_PI * ($i / $len)) * 0.5;
            $x = $i / $rate;
            $out[$start + $i] += (sin(2 * M_PI * $f * $x) * 0.6 + sin(2 * M_PI * $f / 2 * $x) * 0.4) * $env * 0.22;
        }
        $t += 0.5 + $r() * 0.4;
    }
    $rel = 'uploads/assets/music-' . date('Ymd') . '-' . sf_token(8) . '.wav';
    $p = SF_ROOT . '/' . $rel;
    if (!wav_write($p, $out, $rate)) return array('ok' => false, 'error' => 'Could not write audio');
    return array('ok' => true, 'path' => $rel, 'url' => storage_url($rel), 'duration' => $seconds, 'provider' => 'mock.scoresynth', 'status' => 'MOCK');
}
function mockai_sfx($kind = 'Cinematic', $seconds = 2) {
    $r = mockai_rng($kind . $seconds . microtime(true));
    $rate = 22050; $n = (int) ($rate * $seconds);
    $out = array_fill(0, $n, 0.0);
    $last = 0;
    for ($i = 0; $i < $n; $i++) {
        $env = sin(M_PI * ($i / $n));
        if (stripos($kind, 'rain') !== false || stripos($kind, 'crowd') !== false || stripos($kind, 'nature') !== false) {
            $last = $last * 0.6 + ($r() * 2 - 1) * 0.4;         // smoothed noise
        } elseif (stripos($kind, 'door') !== false) {
            $last = ($r() * 2 - 1) * ($i < $n * 0.5 ? 0.5 : 0.15);
        } else {
            $last = ($r() * 2 - 1) * (1 - $i / $n);
        }
        $out[$i] = $last * $env * 0.5;
    }
    $rel = 'uploads/assets/sfx-' . date('Ymd') . '-' . sf_token(8) . '.wav';
    $p = SF_ROOT . '/' . $rel;
    if (!wav_write($p, $out, $rate)) return array('ok' => false, 'error' => 'Could not write audio');
    return array('ok' => true, 'path' => $rel, 'url' => storage_url($rel), 'duration' => $seconds, 'provider' => 'mock.sfxsynth', 'status' => 'MOCK');
}
