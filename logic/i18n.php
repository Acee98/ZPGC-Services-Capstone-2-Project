<?php

if (!function_exists('current_ui_language')) {
    function current_ui_language()
    {
        $lang = (string) ($_SESSION['preferred_language'] ?? '');
        return $lang === 'Filipino' ? 'Filipino' : 'English';
    }

    function zpgc_t($text)
    {
        $text = (string) $text;
        if (current_ui_language() !== 'Filipino' || $text === '') {
            return $text;
        }
        static $map = [
            'Dashboard' => 'Dashboard',
            'Tickets' => 'Mga Ticket',
            'Mailbox' => 'Mensahe',
            'Utilities' => 'Utilities',
            'Performance' => 'Pagganap',
            'Settings' => 'Mga Setting',
            'My Profile' => 'Aking Profile',
            'Profile' => 'Profile',
            'Pending' => 'Nakabinbin',
            'Ongoing' => 'Kasalukuyan',
            'Processing' => 'Pinoproseso',
            'Confirming' => 'Kinukumpirma',
            'Resolved' => 'Nalutas',
            'New Ticket' => 'Bagong Ticket',
            'Subject' => 'Paksa',
            'Description' => 'Paglalarawan',
            'Category' => 'Kategorya',
            'Status' => 'Katayuan',
            'Priority' => 'Prayoridad',
            'Actions' => 'Mga Aksyon',
            'Action' => 'Aksyon',
            'Assigned To' => 'Itinalaga Kay',
            'Unassigned' => 'Walang tekniko',
            'Registration Date & Time' => 'Petsa at Oras ng Pagrehistro',
            'Resolution Time' => 'Oras ng Resolusyon',
            'Queue timer' => 'Oras sa pila',
            'Confirm' => 'Kumpirmahin',
            'View' => 'Tingnan',
            'Save' => 'I-save',
            'Search' => 'Maghanap',
            'Appearance' => 'Hitsura',
            'Theme' => 'Tema',
            'Light' => 'Maliwanag',
            'Dark' => 'Madilim',
            'Save appearance' => 'I-save ang hitsura',
            'Preferred Language' => 'Gustong Wika',
            'English' => 'English',
            'Filipino' => 'Filipino',
            'Personal Information' => 'Personal na Impormasyon',
            'Contact Preferences' => 'Mga Kagustuhan sa Pakikipag-ugnayan',
            'E-mail' => 'E-mail',
            'Name' => 'Pangalan',
            'Account type' => 'Uri ng account',
            'Save profile' => 'I-save ang profile',
            'Hardware' => 'Hardware',
            'Software' => 'Software',
            'Network' => 'Network',
            'Account' => 'Account',
            'Other' => 'Iba pa',
            'Critical' => 'Kritikal',
            'Moderate' => 'Katamtaman',
            'Low' => 'Mababa',
            'None' => 'Wala',
            'Ticket History' => 'Kasaysayan ng Ticket',
            'Resolved and archived tickets' => 'Mga nalutas at naka-archive na ticket',
            'No tickets submitted yet.' => 'Wala pang naisumiteng ticket.',
            'No tickets assigned yet.' => 'Wala pang naitalaga na ticket.',
            'No tickets yet.' => 'Wala pang ticket.',
            'Resolved by Category' => 'Nalutas ayon sa Kategorya',
            'Tickets Resolved' => 'Mga Ticket na Nalutas',
            'Ticket ID' => 'Ticket ID',
            'Satisfaction' => 'Kasiyahan',
            'Technician' => 'Tekniko',
            'Stay in control of how the dashboard looks on this account.' => 'Kontrolin kung paano mukha ang dashboard sa account na ito.',
            'Browser preferences' => 'Mga setting ng browser',
            'Security' => 'Seguridad',
            'Password' => 'Password',
            'Sign-in methods. Keep this password up to date.' => 'Mga paraan ng pag-sign in. Panatilihing updated ang password.',
            'Use at least 8 characters. You will stay signed in after updating.' => 'Gumamit ng hindi bababa sa 8 karakter. Mananatili kang naka-sign in pagkatapos mag-update.',
            'Current password' => 'Kasalukuyang password',
            'New password' => 'Bagong password',
            'Confirm new password' => 'Kumpirmahin ang bagong password',
            'Update password' => 'I-update ang password',
            'E-mail Notifications' => 'Mga abiso sa e-mail',
            'Not set' => 'Hindi nakatakda',
            'Ticket preview' => 'Preview ng ticket',
            'Assigned to' => 'Itinalaga kay',
            'Registered' => 'Nirehistro',
            'Rating' => 'Marka',
            'All' => 'Lahat',
            'Solved' => 'Nalutas',
            'Not Solved Yet' => 'Hindi pa nalutas',
            'Rate visit' => 'I-rate ang bisita',
            'Save ticket' => 'I-save ang ticket',
            'No resolved tickets yet.' => 'Wala pang nalutas na ticket.',
            'No resolved tickets in history yet.' => 'Wala pang naka-archive na ticket.',
            'Closed by reporter' => 'Isinara ng reporter',
        ];
        return $map[$text] ?? $text;
    }

    function zpgc_html_root_attrs()
    {
        $theme = function_exists('current_ui_theme') ? current_ui_theme() : '';
        $auto = $theme === '' ? '1' : '0';
        $applied = ($theme === 'dark' || $theme === 'light') ? $theme : 'light';
        $lang = current_ui_language() === 'Filipino' ? 'fil' : 'en';
        return 'lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8')
            . '" data-theme="' . htmlspecialchars($applied, ENT_QUOTES, 'UTF-8')
            . '" data-theme-auto="' . $auto . '"';
    }

    function zpgc_theme_boot_script()
    {
        return '<script>(function(){var h=document.documentElement;var m=document.cookie.match(/(?:^|; )zpgc_theme=([^;]*)/);var t=m?decodeURIComponent(m[1]):"";if(t==="light"||t==="dark"){h.setAttribute("data-theme",t);h.setAttribute("data-theme-auto","0");return;}if(h.getAttribute("data-theme-auto")==="1"){h.setAttribute("data-theme",window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light");}})();</script>';
    }
}
