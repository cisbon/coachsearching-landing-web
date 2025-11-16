<?php
/**
 * Coaches Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET /coaches - Get all coaches (with search/filter)
if ($route === 'coaches' && $method === 'GET') {
    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 20;
    $search = $_GET['search'] ?? '';
    $specialization = $_GET['specialization'] ?? '';
    $location = $_GET['location'] ?? '';
    $minRating = $_GET['min_rating'] ?? 0;
    $maxPrice = $_GET['max_price'] ?? null;
    $locationType = $_GET['location_type'] ?? ''; // online, in_person
    $lat = $_GET['lat'] ?? null;
    $lng = $_GET['lng'] ?? null;
    $radius = $_GET['radius'] ?? 50; // km

    $pagination = paginate('', $page, $perPage);

    $where = ["u.is_active = 1", "u.role = 'coach'"];
    $params = [];

    // Search by name or bio
    if (!empty($search)) {
        $where[] = "(MATCH(cp.bio, cp.tagline) AGAINST (? IN NATURAL LANGUAGE MODE) OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $params[] = $search;
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // Filter by specialization
    if (!empty($specialization)) {
        $where[] = "JSON_CONTAINS(cp.specializations, ?)";
        $params[] = json_encode($specialization);
    }

    // Filter by location
    if (!empty($location)) {
        $where[] = "(cp.location_city LIKE ? OR cp.location_country LIKE ?)";
        $params[] = "%$location%";
        $params[] = "%$location%";
    }

    // Filter by rating
    if ($minRating > 0) {
        $where[] = "cp.average_rating >= ?";
        $params[] = $minRating;
    }

    // Filter by price
    if ($maxPrice !== null) {
        $where[] = "cp.hourly_rate <= ?";
        $params[] = $maxPrice;
    }

    // Filter by location type
    if ($locationType === 'online') {
        $where[] = "cp.offers_online = 1";
    } elseif ($locationType === 'in_person') {
        $where[] = "cp.offers_in_person = 1";
    }

    $whereClause = implode(' AND ', $where);

    // Get total count
    $countQuery = "
        SELECT COUNT(DISTINCT u.id) as total
        FROM users u
        INNER JOIN coach_profiles cp ON u.id = cp.user_id
        WHERE $whereClause
    ";
    $stmt = $conn->prepare($countQuery);
    $stmt->execute($params);
    $totalCount = $stmt->fetch()['total'];

    // Get coaches
    $query = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.profile_image,
            cp.bio,
            cp.tagline,
            cp.specializations,
            cp.certifications,
            cp.languages,
            cp.hourly_rate,
            cp.currency,
            cp.years_experience,
            cp.location_city,
            cp.location_country,
            cp.location_lat,
            cp.location_lng,
            cp.offers_in_person,
            cp.offers_online,
            cp.average_rating,
            cp.total_reviews,
            cp.total_sessions,
            cp.is_verified,
            cp.is_featured
        FROM users u
        INNER JOIN coach_profiles cp ON u.id = cp.user_id
        WHERE $whereClause
        ORDER BY cp.is_featured DESC, cp.average_rating DESC
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $coaches = $stmt->fetchAll();

    // Decode JSON fields
    foreach ($coaches as &$coach) {
        $coach['specializations'] = json_decode($coach['specializations'] ?? '[]', true);
        $coach['certifications'] = json_decode($coach['certifications'] ?? '[]', true);
        $coach['languages'] = json_decode($coach['languages'] ?? '[]', true);

        // Calculate distance if coordinates provided
        if ($lat && $lng && $coach['location_lat'] && $coach['location_lng']) {
            $coach['distance_km'] = round(calculateDistance($lat, $lng, $coach['location_lat'], $coach['location_lng']), 1);
        }
    }

    sendResponse([
        'coaches' => $coaches,
        'pagination' => [
            'page' => $pagination['page'],
            'per_page' => $pagination['limit'],
            'total' => intval($totalCount),
            'total_pages' => ceil($totalCount / $pagination['limit'])
        ]
    ]);
}

// GET /coaches/profile?id=123 or /coaches/profile?slug=john-doe
if (strpos($route, 'coaches/profile') === 0 && $method === 'GET') {
    $coachId = $_GET['id'] ?? null;
    $slug = $_GET['slug'] ?? null;

    if (!$coachId && !$slug) {
        sendError('Coach ID or slug required', 400);
    }

    $query = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.profile_image,
            u.created_at,
            cp.*
        FROM users u
        INNER JOIN coach_profiles cp ON u.id = cp.user_id
        WHERE u.is_active = 1 AND u.role = 'coach'
    ";

    if ($coachId) {
        $query .= " AND u.id = ?";
        $param = $coachId;
    } else {
        $query .= " AND CONCAT(LOWER(u.first_name), '-', LOWER(u.last_name)) = ?";
        $param = $slug;
    }

    $stmt = $conn->prepare($query);
    $stmt->execute([$param]);
    $coach = $stmt->fetch();

    if (!$coach) {
        sendError('Coach not found', 404);
    }

    // Increment profile views
    $stmt = $conn->prepare("UPDATE coach_profiles SET profile_views = profile_views + 1 WHERE user_id = ?");
    $stmt->execute([$coach['user_id']]);

    // Decode JSON fields
    $coach['specializations'] = json_decode($coach['specializations'] ?? '[]', true);
    $coach['certifications'] = json_decode($coach['certifications'] ?? '[]', true);
    $coach['languages'] = json_decode($coach['languages'] ?? '[]', true);

    // Get coaching sessions
    $stmt = $conn->prepare("
        SELECT * FROM coaching_sessions
        WHERE coach_id = ? AND is_active = 1
        ORDER BY price ASC
    ");
    $stmt->execute([$coach['user_id']]);
    $sessions = $stmt->fetchAll();

    // Get recent reviews
    $stmt = $conn->prepare("
        SELECT
            r.*,
            u.first_name,
            u.last_name,
            u.profile_image
        FROM reviews r
        INNER JOIN users u ON r.reviewer_id = u.id
        WHERE r.coach_id = ? AND r.is_visible = 1
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$coach['user_id']]);
    $reviews = $stmt->fetchAll();

    // Get recent articles
    $stmt = $conn->prepare("
        SELECT id, title, slug, excerpt, featured_image, published_at, views
        FROM articles
        WHERE coach_id = ? AND is_published = 1
        ORDER BY published_at DESC
        LIMIT 5
    ");
    $stmt->execute([$coach['user_id']]);
    $articles = $stmt->fetchAll();

    // Get availability
    $stmt = $conn->prepare("
        SELECT * FROM coach_availability
        WHERE coach_id = ? AND is_active = 1
        ORDER BY day_of_week ASC, start_time ASC
    ");
    $stmt->execute([$coach['user_id']]);
    $availability = $stmt->fetchAll();

    // Check if current user follows this coach
    $isFollowing = false;
    $currentUser = getCurrentUser();
    if ($currentUser) {
        $stmt = $conn->prepare("SELECT id FROM follows WHERE user_id = ? AND coach_id = ?");
        $stmt->execute([$currentUser['user_id'], $coach['user_id']]);
        $isFollowing = (bool)$stmt->fetch();
    }

    sendResponse([
        'coach' => $coach,
        'sessions' => $sessions,
        'reviews' => $reviews,
        'articles' => $articles,
        'availability' => $availability,
        'is_following' => $isFollowing
    ]);
}

// GET /coaches/sessions?coach_id=123
if (strpos($route, 'coaches/sessions') === 0 && $method === 'GET') {
    $coachId = $_GET['coach_id'] ?? null;

    if (!$coachId) {
        sendError('Coach ID required', 400);
    }

    $stmt = $conn->prepare("
        SELECT * FROM coaching_sessions
        WHERE coach_id = ? AND is_active = 1
        ORDER BY price ASC
    ");
    $stmt->execute([$coachId]);
    $sessions = $stmt->fetchAll();

    sendResponse(['sessions' => $sessions]);
}

// GET /coaches/articles?coach_id=123 or /coaches/articles?id=456
if (strpos($route, 'coaches/articles') === 0 && $method === 'GET') {
    $coachId = $_GET['coach_id'] ?? null;
    $articleId = $_GET['id'] ?? null;

    if ($articleId) {
        // Get single article
        $stmt = $conn->prepare("
            SELECT
                a.*,
                u.first_name,
                u.last_name,
                u.profile_image
            FROM articles a
            INNER JOIN users u ON a.coach_id = u.id
            WHERE a.id = ? AND a.is_published = 1
        ");
        $stmt->execute([$articleId]);
        $article = $stmt->fetch();

        if (!$article) {
            sendError('Article not found', 404);
        }

        // Increment views
        $stmt = $conn->prepare("UPDATE articles SET views = views + 1 WHERE id = ?");
        $stmt->execute([$articleId]);

        $article['tags'] = json_decode($article['tags'] ?? '[]', true);

        sendResponse(['article' => $article]);
    } elseif ($coachId) {
        // Get coach's articles
        $stmt = $conn->prepare("
            SELECT * FROM articles
            WHERE coach_id = ? AND is_published = 1
            ORDER BY published_at DESC
        ");
        $stmt->execute([$coachId]);
        $articles = $stmt->fetchAll();

        foreach ($articles as &$article) {
            $article['tags'] = json_decode($article['tags'] ?? '[]', true);
        }

        sendResponse(['articles' => $articles]);
    } else {
        sendError('Coach ID or Article ID required', 400);
    }
}

// If no route matched
sendError('Invalid endpoint or method', 404);
