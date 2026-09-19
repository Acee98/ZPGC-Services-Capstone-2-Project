<?php
/**
 * Shared dashboard status cards — chronological ticket lifecycle.
 * Expects: $status_counts (pending, ongoing, processing, awaiting_confirmation, resolved)
 */
$counts = array_merge(
    [
        'pending'               => 0,
        'ongoing'               => 0,
        'processing'            => 0,
        'awaiting_confirmation' => 0,
        'resolved'              => 0,
    ],
    $status_counts ?? []
);
?>
<div class="status-cards">

    <div class="status-card tech-accent">
        <div class="status-card-info">
            <span class="status-card-count" data-stat="pending"><?= (int) $counts['pending'] ?></span>
            <span class="status-card-label">Pending</span>
        </div>
        <div class="status-card-icon filled" style="--status-color: #000000;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                <path d="M5 2H4v2h1v1c0 2.46 1.32 4.77 3.43 6.02.35.21.57.55.57.9v.16c0 .35-.21.69-.57.9A7.01 7.01 0 0 0 5 19v1H4v2h16v-2h-1v-1c0-2.46-1.32-4.77-3.43-6.02-.36-.21-.57-.55-.57-.9v-.16c0-.35.21-.69.57-.9A7.01 7.01 0 0 0 19 5V4h1V2zm12 3c0 1.76-.94 3.41-2.45 4.3-.97.57-1.55 1.55-1.55 2.62v.16c0 1.07.58 2.05 1.55 2.62 1.51.89 2.45 2.54 2.45 4.3v1H7v-1c0-1.76.94-3.41 2.45-4.3.97-.57 1.55-1.55 1.55-2.62v-.16c0-1.07-.58-2.05-1.55-2.62A5.01 5.01 0 0 1 7 5V4h10z"></path>
            </svg>
        </div>
    </div>

    <div class="status-card tech-accent">
        <div class="status-card-info">
            <span class="status-card-count" data-stat="ongoing"><?= (int) $counts['ongoing'] ?></span>
            <span class="status-card-label">Ongoing</span>
        </div>
        <div class="status-card-icon filled" style="--status-color: #00ABB1;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                <path d="M4.88 8.42 3.1 7.5a10 10 0 0 0-.98 2.95l1.97.32c.13-.81.39-1.6.78-2.35Zm-2.76 5.14c.17 1.02.5 2.01.98 2.94l1.78-.92c-.38-.74-.65-1.53-.78-2.35l-1.97.32ZM4.92 19c.73.74 1.57 1.36 2.48 1.85l.94-1.77c-.73-.39-1.4-.89-1.99-1.49L4.93 19ZM8.33 4.92l-.94-1.77C6.48 3.64 5.64 4.26 4.91 5l1.42 1.41c.59-.6 1.26-1.1 1.99-1.49ZM12 2c-.56 0-1.12.05-1.67.14l.34 1.97c-.44-.08.88-.11 1.32-.11 4.34 0 8 3.66 8 8s-3.66 8-8 8c-.44 0-.89-.04-1.32-.11l-.34 1.97c.55.1 1.11.14 1.67.14 5.42 0 10-4.58 10-10S17.42 2 12 2"></path>
                <path d="M11 7v6h6v-2h-4V7z"></path>
            </svg>
        </div>
    </div>

    <div class="status-card tech-accent">
        <div class="status-card-info">
            <span class="status-card-count" data-stat="processing"><?= (int) $counts['processing'] ?></span>
            <span class="status-card-label">Processing</span>
        </div>
        <div class="status-card-icon filled" style="--status-color: #FF8D28;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                <path d="M18.13 17.13c-.15.18-.31.36-.48.52-.73.74-1.59 1.31-2.54 1.71-1.97.83-4.26.83-6.23 0-.95-.4-1.81-.98-2.54-1.72a7.8 7.8 0 0 1-1.71-2.54c-.42-.99-.63-2.03-.63-3.11H2c0 1.35.26 2.66.79 3.89.5 1.19 1.23 2.26 2.14 3.18s1.99 1.64 3.18 2.14c1.23.52 2.54.79 3.89.79s2.66-.26 3.89-.79c1.19-.5 2.26-1.23 3.18-2.14.17-.17.32-.35.48-.52L22 20.99v-6h-6l2.13 2.13Zm.94-12.2a9.9 9.9 0 0 0-3.18-2.14 10.12 10.12 0 0 0-7.79 0c-1.19.5-2.26 1.23-3.18 2.14-.17.17-.32.35-.48.52L1.99 3v6h6L5.86 6.87c.15-.18.31-.36.48-.52.73-.74 1.59-1.31 2.54-1.71 1.97-.83 4.26-.83 6.23 0 .95.4 1.81.98 2.54 1.72.74.73 1.31 1.59 1.71 2.54.42.99.63 2.03.63 3.11h2c0-1.35-.26-2.66-.79-3.89-.5-1.19-1.23-2.26-2.14-3.18Z"></path>
            </svg>
        </div>
    </div>

    <div class="status-card tech-accent">
        <div class="status-card-info">
            <span class="status-card-count" data-stat="awaiting_confirmation"><?= (int) $counts['awaiting_confirmation'] ?></span>
            <span class="status-card-label" title="Awaiting Confirmation">Confirming</span>
        </div>
        <div class="status-card-icon filled" style="--status-color: #7B61FF;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                <path d="m11 11.59-1.29-1.3-1.42 1.42 2.71 2.7 4.74-4.7-1.41-1.42z"></path>
                <path d="M19 12.59V10c0-3.22-2.18-5.93-5.14-6.74C13.57 2.52 12.85 2 12 2s-1.56.52-1.86 1.26C7.18 4.08 5 6.79 5 10v2.59L3.29 14.3a1 1 0 0 0-.29.71v2c0 .55.45 1 1 1h16c.55 0 1-.45 1-1v-2c0-.27-.11-.52-.29-.71zM19 16H5v-.59l1.71-1.71a1 1 0 0 0 .29-.71v-3c0-2.76 2.24-5 5-5s5 2.24 5 5v3c0 .27.11.52.29.71L19 15.41zm-7 6c1.31 0 2.41-.83 2.82-2H9.18c.41 1.17 1.51 2 2.82 2"></path>
            </svg>
        </div>
    </div>

    <div class="status-card tech-accent">
        <div class="status-card-info">
            <span class="status-card-count" data-stat="resolved"><?= (int) $counts['resolved'] ?></span>
            <span class="status-card-label">Resolved</span>
        </div>
        <div class="status-card-icon filled" style="--status-color: #34C759;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 22C6.49 22 2 17.51 2 12S6.49 2 12 2s10 4.49 10 10-4.49 10-10 10m0-18c-4.41 0-8 3.59-8 8s3.59 8 8 8 8-3.59 8-8-3.59-8-8-8"></path>
                <path d="M10 16c-.26 0-.51-.1-.71-.29l-3-3L7.7 11.3l2.29 2.29 5.29-5.29 1.41 1.41-6 6c-.2.2-.45.29-.71.29Z"></path>
            </svg>
        </div>
    </div>

</div>
