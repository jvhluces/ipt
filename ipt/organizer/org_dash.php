<?php

session_start();

require_once '../config/db.php';
 
if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}





if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Organizer'
) {
    header("Location: ../auth/login_user.php");
    exit();
}


$user_id = intval($_SESSION['user_id']);




$userQuery = mysqli_query(
    $conn,
    "SELECT *
     FROM users
     WHERE user_id = $user_id
     LIMIT 1"
);


if (!$userQuery || mysqli_num_rows($userQuery) === 0) {

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: ../auth/login_user.php");
    exit();
}


$userData = mysqli_fetch_assoc($userQuery);
 
$userName = $userData['fullname'] ?? 'Organizer';
$avatar = '';
if (!empty($userData['profile_pic'])) {
    $storedAvatar = ltrim(str_replace('\\', '/', (string)$userData['profile_pic']), '/');
    $avatarCandidates = [
        '../' . $storedAvatar,
        '../uploads/profiles/' . basename($storedAvatar),
        '../uploads/profile/' . basename($storedAvatar),
        '../uploads/' . basename($storedAvatar),
        '../assets/uploads/profiles/' . basename($storedAvatar),
        '../assets/images/' . basename($storedAvatar)
    ];
    foreach ($avatarCandidates as $candidate) {
        if (is_file(__DIR__ . '/' . $candidate)) {
            $avatar = $candidate;
            break;
        }
    }
}
if ($avatar === '') {
    $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=0d6efd&color=fff&bold=true';
}





$accountStatus = $userData['account_status'] ?? 'Active';

if (strcasecmp($accountStatus, 'Active') !== 0) {

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: ../auth/login_user.php?account=inactive");
    exit();
}




$organizerName =
    $userData['fullname'] ?? 'Organizer';

$organizerEmail =
    $userData['email'] ?? '';

$organizerContact =
    $userData['contact'] ?? '';

$organizationName =
    $userData['organization_name'] ?? '';




function getCount($conn, $uid, $category)
{
    $uid = intval($uid);

    $category = mysqli_real_escape_string(
        $conn,
        $category
    );

    $query = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM events
         WHERE organizer_id = $uid
         AND category = '$category'"
    );

    if ($query) {

        $result = mysqli_fetch_assoc($query);

        return $result['total'] ?? 0;
    }

    return 0;
}




$categories = [

    'Wedding' => [
        'icon' => 'heart-fill',
        'color' => 'danger',
        'description' =>
            'Weddings, receptions, and marriage celebrations.'
    ],

    'Party' => [
        'icon' => 'balloon-fill',
        'color' => 'warning',
        'description' =>
            'Birthday parties, celebrations, and social events.'
    ],

    'Meeting' => [
        'icon' => 'briefcase-fill',
        'color' => 'primary',
        'description' =>
            'Business meetings and organizational gatherings.'
    ],

    'Seminar' => [
        'icon' => 'person-video3',
        'color' => 'dark',
        'description' =>
            'Seminars, talks, and educational sessions.'
    ],

    'Workshop' => [
        'icon' => 'tools',
        'color' => 'info',
        'description' =>
            'Hands-on workshops and training activities.'
    ],

    'Community Event' => [
        'icon' => 'people-fill',
        'color' => 'success',
        'description' =>
            'Community programs and public activities.'
    ]

];




$searchEvents = [];

$searchQuery = mysqli_query(
    $conn,
    "SELECT
        event_id,
        event_name,
        description,
        event_date,
        location,
        category,
        status
     FROM events
     WHERE organizer_id = $user_id
     ORDER BY event_date ASC"
);


if ($searchQuery) {

    while ($event = mysqli_fetch_assoc($searchQuery)) {

        $searchEvents[] = $event;
    }
}




$profileImage = '';

if (!empty($userData['profile_pic'])) {

    $cleanProfile = ltrim(
        str_replace('\\', '/', $userData['profile_pic']),
        '/'
    );

    $possibleProfilePaths = [

        '../' . $cleanProfile,

        '../uploads/profiles/' .
        basename($cleanProfile),

        '../uploads/profile/' .
        basename($cleanProfile),

        '../uploads/' .
        basename($cleanProfile),

        '../assets/uploads/profiles/' .
        basename($cleanProfile),

        '../assets/images/' .
        basename($cleanProfile)

    ];

    foreach ($possibleProfilePaths as $path) {

        if (file_exists($path)) {

            $profileImage = $path;

            break;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Organizer Dashboard - Event System
    </title>


    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/organizer/org_dash.css">
    <link rel="stylesheet" href="../assets/css/organizer/sidebar.css">

</head>


<body>




<aside
    class="sidebar"
    id="sidebar"
>


    <div class="sidebar-header">

        <a href="org_dash.php" class="brand">

            <i class="bi bi-calendar-event"></i>

            Event System

        </a>


        <button
            type="button"
            class="close-sidebar"
            onclick="closeSidebar()"
            aria-label="Close menu"
        >

            <i class="bi bi-x-lg"></i>

        </button>

    </div>


 

     <div class="user-profile">

            <img
                src="<?= e($avatar); ?>"
                alt="Profile"
                onerror="
                    this.onerror=null;
                    this.src='https://ui-avatars.com/api/?name=<?= urlencode($userName); ?>&background=0d6efd&color=fff&bold=true';
                "
            >

            <span class="user-profile-name">

                <?= e($userName); ?>

            </span>

        </div>

  

    <div class="sidebar-menu">


        <div class="sidebar-label">
            Main Menu
        </div>


       <a 
    href="#home"
    class="active"
    onclick="
        showSection('home', this); 
        return false;
    "
>
    
            <i class="bi bi-house-door"></i>

            Home

        </a>


        <a href="../organizer/all_events.php">

            <i class="bi bi-calendar3"></i>

            All Events

        </a>


        <a href="../organizer/event_reviews.php">

            <i class="bi bi-star"></i>

            Event Reviews

        </a>


        <div class="sidebar-label">
            Account
        </div>


        <a 
    href="#profile"
    onclick="
        showSection('profile', this);
        return false;
    "
>
    <i class="bi bi-person"></i>
    My Profile
</a>


        <a 
    href="#settings"
    onclick="
        showSection('settings', this);
        return false;
    "
>
    <i class="bi bi-gear"></i>
    Settings
</a>

        <a href="help.php">

            <i class="bi bi-question-circle"></i>

            Help & Support

        </a>


    </div>


    <div class="sidebar-footer">

        <a
            href="../auth/logout_user.php"
            class="logout-link"
            onclick="return confirm('Are you sure you want to logout?');"
        >
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>

    </div>

</aside>
 

<div
    class="overlay"
    id="overlay"
    onclick="closeSidebar()"
></div>




<nav class="top-navbar">


    <button
        type="button"
        class="menu-btn"
        onclick="toggleSidebar()"
        aria-label="Open menu"
    >

        <i class="bi bi-list"></i>

    </button>


    <span class="nav-title">

        Event System

    </span>


</nav>



<main class="main-content">



<div
    id="home"
    class="dashboard-section"
>



    <div class="welcome-card">

        <h1>

            Welcome,

            <?= htmlspecialchars($organizerName); ?>!

        </h1>


        <p>

            Manage your events by category
            and create new events easily.

        </p>

    </div>



    <div class="event-search-section">


        <div class="event-search-header">

            <h4>

                <i class="bi bi-search text-primary"></i>

                Search Your Events

            </h4>


            <p>

                Search for a specific event by name,
                category, location, or status.

            </p>

        </div>


        <div class="search-box-wrapper">

            <i class="bi bi-search search-box-icon"></i>


            <input
                type="text"
                id="eventSearchInput"
                class="event-search-input"
                placeholder="Search event name..."
                autocomplete="off"
            >


            <button
                type="button"
                id="clearSearchBtn"
                class="clear-search-btn"
                title="Clear search"
            >

                <i class="bi bi-x-circle-fill"></i>

            </button>

        </div>


        <div
            id="searchResults"
            class="search-results"
        ></div>


    </div>




    <div class="category-section">


        <div class="category-section-header">

            <div>

                <h2>

                    <i class="bi bi-grid-fill text-primary"></i>

                    Event Categories

                </h2>


                <p>

                    Choose a category to view
                    and manage its events.

                </p>

            </div>

        </div>


        <div class="category-grid">


            <?php foreach (
                $categories
                as $categoryName => $categoryInfo
            ): ?>


                <?php

                $eventTotal =
                    getCount(
                        $conn,
                        $user_id,
                        $categoryName
                    );

                ?>


                <div
                    class="category-card"
                    onclick="
                        window.location.href =
                        'category_events.php?category=<?= urlencode($categoryName); ?>';
                    "
                >


                    <div
                        class="
                            category-icon
                            icon-<?= htmlspecialchars($categoryInfo['color']); ?>
                        "
                    >

                        <i
                            class="
                                bi
                                bi-<?= htmlspecialchars($categoryInfo['icon']); ?>
                            "
                        ></i>

                    </div>


                    <h4>

                        <?= htmlspecialchars($categoryName); ?>

                    </h4>


                    <p>

                        <?= htmlspecialchars($categoryInfo['description']); ?>

                    </p>


                    <div class="event-count">

                        <span>

                            <i class="bi bi-calendar-event"></i>

                            My Events

                        </span>


                        <strong
                            class="
                                text-<?= htmlspecialchars($categoryInfo['color']); ?>
                            "
                        >

                            <?= $eventTotal; ?>

                        </strong>

                    </div>


                    <div class="category-arrow">

                        <i class="bi bi-arrow-right"></i>

                    </div>


                </div>


            <?php endforeach; ?>


        </div>

    </div>


   
    <div class="info-card">


        <h5>

            <i class="bi bi-info-circle text-primary"></i>

            How to Manage Your Events

        </h5>


        <div class="info-item">

            <i class="bi bi-1-circle-fill"></i>

            <span>

                Select an event category above.

            </span>

        </div>


        <div class="info-item">

            <i class="bi bi-2-circle-fill"></i>

            <span>

                View your events grouped into
                Upcoming, Ongoing, and Ended.

            </span>

        </div>


        <div class="info-item">

            <i class="bi bi-3-circle-fill"></i>

            <span>

                Upcoming events can be edited
                or deleted.

            </span>

        </div>


        <div class="info-item">

            <i class="bi bi-4-circle-fill"></i>

            <span>

                Ongoing events can be edited
                but cannot be deleted.

            </span>

        </div>


        <div class="info-item">

            <i class="bi bi-5-circle-fill"></i>

            <span>

                Ended events can be archived.

            </span>

        </div>


    </div>


</div>




<div
    id="profile"
    class="dashboard-section"
    style="display: none;"
>


    <div class="profile-card">


        <div class="profile-avatar">


            <?php if (!empty($profileImage)): ?>

                <img
                    src="<?= htmlspecialchars($profileImage); ?>"
                    alt="Profile"
                >

            <?php else: ?>

                <i class="bi bi-person-fill"></i>

            <?php endif; ?>


        </div>


        <h3 class="text-center mb-4">

            My Profile

        </h3>


        <div class="alert alert-info">

            <i class="bi bi-info-circle me-1"></i>

            View and manage your organizer account
            information below.

        </div>


      

        <div class="mb-3">

            <label class="form-label fw-bold">

                <i class="bi bi-person me-1"></i>

                Full Name

            </label>


            <input
                type="text"
                class="form-control"
                value="<?= htmlspecialchars($organizerName); ?>"
                readonly
            >

        </div>


        <div class="mb-3">

            <label class="form-label fw-bold">

                <i class="bi bi-envelope me-1"></i>

                Email

            </label>


            <input
                type="email"
                class="form-control"
                value="<?= htmlspecialchars($organizerEmail); ?>"
                readonly
            >

        </div>



        <div class="mb-3">

            <label class="form-label fw-bold">

                <i class="bi bi-telephone me-1"></i>

                Contact Number

            </label>


            <input
                type="text"
                class="form-control"
                value="<?= htmlspecialchars($organizerContact); ?>"
                readonly
            >

        </div>



        <div class="mb-3">

            <label class="form-label fw-bold">

                <i class="bi bi-building me-1"></i>

                Organization Name

            </label>


            <input
                type="text"
                class="form-control"
                value="<?= htmlspecialchars($organizationName); ?>"
                readonly
            >

        </div>


        <a
            href="../actions/update_profile.php"
            class="btn btn-primary w-100 mb-4"
        >

            <i class="bi bi-pencil-square me-1"></i>

            Edit Profile

        </a>


        <hr>


        <div class="mt-4">

            <h4 class="fw-bold mb-2">

                <i class="bi bi-person-gear me-1"></i>

                Account Management

            </h4>


            <p class="text-muted">

                You can temporarily deactivate your account
                or permanently delete it.

            </p>


            <form
                action="../actions/user_account_action.php"
                method="POST"
                class="mb-3"
                onsubmit="
                    return confirm(
                        'Are you sure you want to deactivate your account?\n\nYour account will become inactive and you will be logged out immediately.'
                    );
                "
            >

                <input
                    type="hidden"
                    name="action"
                    value="deactivate"
                >


                <button
                    type="submit"
                    class="btn btn-outline-warning w-100"
                >

                    <i class="bi bi-person-slash me-1"></i>

                    Deactivate Account

                </button>

            </form>



            <form
                action="../actions/user_account_action.php"
                method="POST"
                onsubmit="
                    return confirm(
                        'WARNING!\n\nDeleting your account is permanent. Your organizer account and your event data will be permanently deleted.\n\nThis action cannot be undone.\n\nDo you want to continue?'
                    );
                "
            >

                <input
                    type="hidden"
                    name="action"
                    value="delete"
                >


                <button
                    type="submit"
                    class="btn btn-outline-danger w-100"
                >

                    <i class="bi bi-trash3 me-1"></i>

                    Delete Account Permanently

                </button>

            </form>


        </div>


    </div>


</div>


<div
    id="settings"
    class="dashboard-section"
    style="display: none;"
>


    <div class="profile-card">


        <h3 class="mb-4">

            <i class="bi bi-gear me-1"></i>

            Settings

        </h3>


      

        <div class="mb-4">

            <label class="form-label fw-bold">

                <i class="bi bi-person me-1"></i>

                Username

            </label>


            <input
                type="text"
                id="settingsUsername"
                class="form-control"
                value="<?= htmlspecialchars(
                    $userData['username'] ?? ''
                ); ?>"
                readonly
            >


            <small class="text-muted">

                Your username is used when logging in.

            </small>

        </div>


        <a
            href="../actions/change_username.php"
            class="btn btn-primary w-100 mb-4"
        >

            <i class="bi bi-pencil-square me-1"></i>

            Change Username

        </a>


        <hr>


       

        <div class="mt-4 mb-3">

            <h5 class="fw-bold">

                <i class="bi bi-lock me-1"></i>

                Change Password

            </h5>


            <p class="text-muted">

                Update your password for your next login.

            </p>

        </div>


        <a
            href="../actions/change_user_password.php"
            class="btn btn-outline-primary w-100"
        >

            <i class="bi bi-key me-1"></i>

            Change Password

        </a>


    </div>


</div>


</main>




<script>

    const organizerEvents =
        <?= json_encode(
            $searchEvents,
            JSON_HEX_TAG |
            JSON_HEX_QUOT |
            JSON_HEX_AMP
        ); ?>;

</script>


<script
    src="../assets/js/organizer/org_dash.js"
></script>


</body>

</html>