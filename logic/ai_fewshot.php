<?php
/**
 * Campus few-shot examples for classification.
 * Hand-written ZPGC-style tickets (not taken from the Kaggle test sample).
 */
if (!function_exists('ai_fewshot_classify_messages')) {
    function ai_fewshot_classify_messages()
    {
        $pairs = [
            [
                'Subject: Computer will not turn on',
                'Description: The laboratory PC has no power. We already checked the wall outlet.',
                '{"category":"hardware","urgency":3,"impact":2,"confidence":0.92,"rationale":"Physical PC will not power on in a lab"}',
            ],
            [
                'Subject: Microsoft Word keeps crashing',
                'Description: Word closes when I open a document on the faculty workstation.',
                '{"category":"software","urgency":2,"impact":1,"confidence":0.9,"rationale":"Office application crash for one user"}',
            ],
            [
                'Subject: Cannot connect to campus Wi-Fi',
                'Description: Laptops in the classroom cannot join the wireless network. The whole class is waiting.',
                '{"category":"network","urgency":3,"impact":2,"confidence":0.93,"rationale":"Campus wireless outage affecting a class"}',
            ],
            [
                'Subject: Account locked after OTP',
                'Description: I cannot sign in. The portal says my username is locked after the one-time password.',
                '{"category":"account","urgency":2,"impact":1,"confidence":0.94,"rationale":"Login / OTP lock for one person"}',
            ],
            [
                'Subject: How do I reserve the computer lab',
                'Description: I need the schedule and the request form for the IT office this week.',
                '{"category":"other","urgency":1,"impact":1,"confidence":0.88,"rationale":"Process question, not a broken device"}',
            ],
            [
                'Subject: I need equipment checked',
                'Description: The room 414 PC has a Windows Update.',
                '{"category":"software","urgency":1,"impact":1,"confidence":0.9,"rationale":"Windows Update is software even if the subject says equipment"}',
            ],
        ];
        $msgs = [];
        foreach ($pairs as $row) {
            $msgs[] = [
                'role' => 'user',
                'content' => $row[0] . "\n" . $row[1],
            ];
            $msgs[] = [
                'role' => 'assistant',
                'content' => $row[2],
            ];
        }
        return $msgs;
    }
}
