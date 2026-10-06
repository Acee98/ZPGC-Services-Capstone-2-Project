<?php

if (!function_exists('ticket_subject_word_limit')) {
    function ticket_subject_word_limit()
    {
        return 15;
    }

    function ticket_description_word_limit()
    {
        // A submit sends this text to classification, and a Low ticket can ask
        // for troubleshooting too. Fifteen words plus eighty keeps that pair
        // near a few hundred tokens, so a $5 credit covers a campus demo.
        return 80;
    }

    function ticket_common_questions()
    {
        return [
            'hardware' => [
                'Monitor display problem',
                'Computer will not turn on',
                'Keyboard or mouse is not working',
                'Printer is not working',
            ],
            'software' => [
                'An application keeps crashing',
                'I cannot open an application',
                'I need a program installed',
                'Software is running slow',
            ],
            'network' => [
                'Internet problem',
                'Cannot connect to the Wi-Fi',
                'The Wi-Fi is slow',
                'A website will not open',
            ],
            'account' => [
                'Locked out from my account',
                'I forgot my password',
                'Changing profile picture',
                'I cannot sign in to my account',
            ],
            'other' => [
                'A room needs a check',
                'I need equipment checked',
                'I have a general question',
            ],
        ];
    }

    function ticket_word_count($text)
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
        if ($text === '') {
            return 0;
        }
        return count(preg_split('/\s+/u', $text));
    }

    function ticket_subject_from_post($subject, $description)
    {
        $subject = trim(preg_replace('/\s+/u', ' ', (string) $subject));
        $description = trim(preg_replace('/\s+/u', ' ', (string) $description));

        if ($subject === '') {
            return ['ok' => false, 'error' => 'Choose a common subject, or type your own.'];
        }
        if (ticket_word_count($subject) > ticket_subject_word_limit()) {
            return ['ok' => false, 'error' => 'Subject can be at most ' . ticket_subject_word_limit() . ' words.'];
        }
        if (strlen($subject) > 255) {
            return ['ok' => false, 'error' => 'Subject is too long.'];
        }
        if (ticket_word_count($description) === 0) {
            return ['ok' => false, 'error' => 'Write a description.'];
        }
        if (ticket_word_count($description) > ticket_description_word_limit()) {
            return ['ok' => false, 'error' => 'Description can be at most ' . ticket_description_word_limit() . ' words.'];
        }
        return ['ok' => true, 'subject' => $subject, 'description' => $description];
    }
}
