<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/main_interface.css">
    <title>ZPGC Services | Administrator
    </title>
</head>

<body data-page="dashboard">
    <main class="main-wrap">
        <header class="main-head">
            <div class="main-nav">
                <nav class="navbar">
                    <div class="navbar-nav">
                        <div class="logo">
                            <img src="../images/ZPGC.com2.png" alt="ZPGC">
                            <button class="showcase-toggler">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                    viewBox="0 0 24 24">
                                    <path d="M3 4h18v2H3zm0 7h18v2H3zm0 7h18v2H3z"></path>
                                </svg>
                            </button>
                        </div>
                        <ul class="nav-list">
                            <li class="nav-list-item selected" data-nav="dashboard">
                                <a href="#" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M20 11h-6c-.55 0-1 .45-1 1v8c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-8c0-.55-.45-1-1-1m-1 8h-4v-6h4zm-9-4H4c-.55 0-1 .45-1 1v4c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-4c0-.55-.45-1-1-1m-1 4H5v-2h4zM20 3h-6c-.55 0-1 .45-1 1v4c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1m-1 4h-4V5h4zm-9-4H4c-.55 0-1 .45-1 1v8c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1m-1 8H5V5h4z">
                                        </path>
                                    </svg>
                                    <span class="link-text">Dashboard</span>
                                </a>
                            </li>
                            <li class="nav-list-item" data-nav="tickets">
                                <a href="#" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M20.71 6.04a.99.99 0 0 0-.9.27l-3.18 3.18-2.12-2.12 3.18-3.18a.98.98 0 0 0 .27-.9c-.07-.33-.29-.6-.6-.73A7.47 7.47 0 0 0 9.2 4.19a7.49 7.49 0 0 0-1.86 7.52L2.3 16.75c-.19.19-.29.44-.29.71s.11.52.29.71l3.54 3.54c.19.19.44.29.71.29s.52-.11.71-.29l5.04-5.04c2.64.82 5.53.12 7.52-1.86a7.47 7.47 0 0 0 1.63-8.16c-.13-.31-.4-.53-.73-.6Zm-2.32 7.34a5.51 5.51 0 0 1-5.98 1.2c-.37-.15-.8-.07-1.09.22l-4.78 4.78-2.12-2.12 4.78-4.78c.29-.29.37-.71.22-1.09a5.47 5.47 0 0 1 1.2-5.98 5.5 5.5 0 0 1 4.41-1.59l-2.65 2.65a.996.996 0 0 0 0 1.41l3.54 3.54c.19.19.44.29.71.29s.52-.11.71-.29l2.65-2.65c.16 1.61-.4 3.23-1.59 4.42Z">
                                        </path>
                                    </svg>
                                    <span class="link-text">Utilities</span>
                                </a>
                            </li>
                            <li class="nav-list-item" data-nav="analytics">
                                <a href="#" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path d="M4 2H2v19c0 .55.45 1 1 1h19v-2H4z"></path>
                                        <path d="M17 12h2v6h-2zm-5-8h2v14h-2zM7 9h2v9H7z"></path>
                                    </svg>
                                    <span class="link-text">Analytics</span>
                                </a>
                            </li>
                            <li class="nav-list-item" data-nav="messages">
                                <a href="#" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 2v.51l-8 6.22-8-6.22V6zM4 18V9.04l7.39 5.74c.18.14.4.21.61.21s.43-.07.61-.21L20 9.03v8.96H4Z">
                                        </path>
                                    </svg>
                                    <span class="link-text">Mailbox</span>
                                </a>
                            </li>
                            <li class="nav-list-item" data-nav="settings">
                                <a href="#" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4m0 6c-1.08 0-2-.92-2-2s.92-2 2-2 2 .92 2 2-.92 2-2 2">
                                        </path>
                                        <path
                                            d="m20.42 13.4-.51-.29c.05-.37.08-.74.08-1.11s-.03-.74-.08-1.11l.51-.29c.96-.55 1.28-1.78.73-2.73l-1-1.73a2.006 2.006 0 0 0-2.73-.73l-.53.31c-.58-.46-1.22-.83-1.9-1.11v-.6c0-1.1-.9-2-2-2h-2c-1.1 0-2 .9-2 2v.6c-.67.28-1.31.66-1.9 1.11l-.53-.31c-.96-.55-2.18-.22-2.73.73l-1 1.73c-.55.96-.22 2.18.73 2.73l.51.29c-.05.37-.08.74-.08 1.11s.03.74.08 1.11l-.51.29c-.96.55-1.28 1.78-.73 2.73l1 1.73c.55.95 1.77 1.28 2.73.73l.53-.31c.58.46 1.22.83 1.9 1.11v.6c0 1.1.9 2 2 2h2c1.1 0 2-.9 2-2v-.6a8.7 8.7 0 0 0 1.9-1.11l.53.31c.95.55 2.18.22 2.73-.73l1-1.73c.55-.96.22-2.18-.73-2.73m-2.59-2.78c.11.45.17.92.17 1.38s-.06.92-.17 1.38a1 1 0 0 0 .47 1.11l1.12.65-1 1.73-1.14-.66c-.38-.22-.87-.16-1.19.14-.68.65-1.51 1.13-2.38 1.4-.42.13-.71.52-.71.96v1.3h-2v-1.3c0-.44-.29-.83-.71-.96-.88-.27-1.7-.75-2.38-1.4a1.01 1.01 0 0 0-1.19-.15l-1.14.66-1-1.73 1.12-.65c.39-.22.58-.68.47-1.11-.11-.45-.17-.92-.17-1.38s.06-.93.17-1.38A1 1 0 0 0 5.7 9.5l-1.12-.65 1-1.73 1.14.66c.38.22.87.16 1.19-.14.68-.65 1.51-1.13 2.38-1.4.42-.13.71-.52.71-.96v-1.3h2v1.3c0 .44.29.83.71.96.88.27 1.7.75 2.38 1.4.32.31.81.36 1.19.14l1.14-.66 1 1.73-1.12.65c-.39.22-.58.68-.47 1.11Z">
                                        </path>
                                    </svg>
                                    <span class="link-text" id="settings">Settings</span>
                                </a>
                            </li>
                            <li class="nav-list-item">
                                <a href="../pages/login_signup.php" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path d="M9 13h7v-2H9V7l-6 5 6 5z"></path>
                                        <path d="M19 3h-7v2h7v14h-7v2h7c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2"></path>
                                    </svg>
                                    <span class="link-text" id="logout">Logout</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </header>
        <div class="sidebar-spacer"></div>
        <section class="showcase">
            <div class="head">
                <header>
                    <h1>Dashboard</h1>
                    <div class="search-bar-wrapper">
                        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                            fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M18 10c0-4.41-3.59-8-8-8s-8 3.59-8 8 3.59 8 8 8c1.85 0 3.54-.63 4.9-1.69l5.1 5.1L21.41 20l-5.1-5.1A8 8 0 0 0 18 10M4 10c0-3.31 2.69-6 6-6s6 2.69 6 6-2.69 6-6 6-6-2.69-6-6">
                            </path>
                        </svg>
                        <input type="search" class="search-bar" placeholder="Search" aria-label="Search">
                    </div>
                    <div class="profile-circle"></div>
                </header>
            </div>
        </section>
    </main>
    <script src="../js/behavior.js"></script>
</body>

</html>