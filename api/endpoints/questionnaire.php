<?php
/**
 * Questionnaire Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET /questionnaire - Get questionnaire questions
if ($route === 'questionnaire' && $method === 'GET') {
    $userType = $_GET['user_type'] ?? 'guest';

    $questions = [
        [
            'id' => 'coaching_area',
            'type' => 'select',
            'question' => 'What area of coaching are you interested in?',
            'required' => true,
            'options' => [
                'business' => 'Business Coaching',
                'executive' => 'Executive Coaching',
                'career' => 'Career Coaching',
                'life' => 'Life Coaching',
                'health' => 'Health & Wellness Coaching',
                'fitness' => 'Fitness Coaching',
                'nutrition' => 'Nutrition Coaching',
                'mindfulness' => 'Mindfulness & Meditation',
                'relationship' => 'Relationship Coaching',
                'leadership' => 'Leadership Development',
                'performance' => 'Performance Coaching',
                'financial' => 'Financial Coaching'
            ]
        ],
        [
            'id' => 'specific_goals',
            'type' => 'multiselect',
            'question' => 'What specific goals do you want to achieve? (Select all that apply)',
            'required' => true,
            'options' => [
                'career_growth' => 'Career advancement',
                'skill_development' => 'Skill development',
                'work_life_balance' => 'Work-life balance',
                'confidence' => 'Build confidence',
                'stress_management' => 'Stress management',
                'productivity' => 'Increase productivity',
                'weight_loss' => 'Weight loss',
                'muscle_gain' => 'Muscle gain',
                'better_relationships' => 'Improve relationships',
                'leadership_skills' => 'Develop leadership skills',
                'public_speaking' => 'Public speaking',
                'financial_freedom' => 'Financial freedom'
            ]
        ],
        [
            'id' => 'session_format',
            'type' => 'radio',
            'question' => 'Do you prefer in-person or online sessions?',
            'required' => true,
            'options' => [
                'online' => 'Online sessions',
                'in_person' => 'In-person sessions',
                'both' => 'Open to both'
            ]
        ],
        [
            'id' => 'location',
            'type' => 'text',
            'question' => 'What is your location? (City, Country)',
            'required' => false,
            'conditional' => [
                'field' => 'session_format',
                'values' => ['in_person', 'both']
            ]
        ],
        [
            'id' => 'budget',
            'type' => 'select',
            'question' => 'What is your budget per session?',
            'required' => false,
            'options' => [
                '0-50' => 'Under €50',
                '50-100' => '€50 - €100',
                '100-150' => '€100 - €150',
                '150-200' => '€150 - €200',
                '200+' => 'Over €200'
            ]
        ],
        [
            'id' => 'frequency',
            'type' => 'radio',
            'question' => 'How often would you like to meet with your coach?',
            'required' => false,
            'options' => [
                'weekly' => 'Once a week',
                'biweekly' => 'Every two weeks',
                'monthly' => 'Once a month',
                'flexible' => 'Flexible schedule'
            ]
        ],
        [
            'id' => 'experience_level',
            'type' => 'radio',
            'question' => 'Have you worked with a coach before?',
            'required' => false,
            'options' => [
                'never' => 'Never',
                'once' => 'Once or twice',
                'experienced' => 'Yes, I have experience with coaching'
            ]
        ],
        [
            'id' => 'preferred_language',
            'type' => 'select',
            'question' => 'What language do you prefer for sessions?',
            'required' => false,
            'options' => [
                'en' => 'English',
                'de' => 'German',
                'es' => 'Spanish',
                'fr' => 'French',
                'it' => 'Italian',
                'nl' => 'Dutch',
                'pt' => 'Portuguese'
            ]
        ]
    ];

    // Add business-specific questions
    if ($userType === 'business') {
        $questions[] = [
            'id' => 'team_size',
            'type' => 'select',
            'question' => 'How many people will be participating in coaching?',
            'required' => false,
            'options' => [
                '1' => 'Just myself',
                '2-5' => '2-5 people',
                '6-10' => '6-10 people',
                '11-20' => '11-20 people',
                '20+' => 'More than 20 people'
            ]
        ];

        $questions[] = [
            'id' => 'duration',
            'type' => 'select',
            'question' => 'What is your preferred engagement duration?',
            'required' => false,
            'options' => [
                '1-3' => '1-3 months',
                '3-6' => '3-6 months',
                '6-12' => '6-12 months',
                '12+' => 'More than 12 months'
            ]
        ];
    }

    sendResponse(['questions' => $questions]);
}

// POST /questionnaire/submit - Submit questionnaire and get matches
if ($route === 'questionnaire/submit' && $method === 'POST') {
    $data = getInputData();
    $answers = $data['answers'] ?? [];

    if (empty($answers)) {
        sendError('Answers required', 400);
    }

    // Get user info if authenticated
    $currentUser = getCurrentUser();
    $userId = $currentUser['user_id'] ?? null;
    $userType = $currentUser['role'] ?? 'guest';

    // Save questionnaire response
    try {
        $sessionId = session_id() ?: generateRandomString(128);

        $stmt = $conn->prepare("
            INSERT INTO questionnaire_responses (user_id, session_id, user_type, answers)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $sessionId,
            $userType,
            json_encode($answers)
        ]);

        $responseId = $conn->lastInsertId();

    } catch (PDOException $e) {
        error_log("Questionnaire submission error: " . $e->getMessage());
    }

    // Match coaches based on answers
    $matches = matchCoaches($conn, $answers);

    // Update response with matched coaches
    if (isset($responseId)) {
        $stmt = $conn->prepare("
            UPDATE questionnaire_responses
            SET matched_coaches = ?
            WHERE id = ?
        ");
        $stmt->execute([
            json_encode(array_column($matches, 'id')),
            $responseId
        ]);
    }

    sendResponse([
        'message' => 'Questionnaire submitted successfully',
        'matches' => $matches,
        'total_matches' => count($matches)
    ]);
}

/**
 * Match coaches based on questionnaire answers
 */
function matchCoaches($conn, $answers) {
    $where = ["u.is_active = 1", "u.role = 'coach'"];
    $params = [];
    $scores = [];

    // Filter by coaching area/specialization
    if (!empty($answers['coaching_area'])) {
        $where[] = "JSON_CONTAINS(cp.specializations, ?)";
        $params[] = json_encode($answers['coaching_area']);
    }

    // Filter by session format
    if (!empty($answers['session_format'])) {
        if ($answers['session_format'] === 'online') {
            $where[] = "cp.offers_online = 1";
        } elseif ($answers['session_format'] === 'in_person') {
            $where[] = "cp.offers_in_person = 1";
        }
    }

    // Filter by budget
    if (!empty($answers['budget'])) {
        $budgetRange = explode('-', $answers['budget']);
        if (count($budgetRange) === 2) {
            $where[] = "cp.hourly_rate BETWEEN ? AND ?";
            $params[] = floatval($budgetRange[0]);
            $params[] = floatval($budgetRange[1]);
        } elseif ($answers['budget'] === '200+') {
            $where[] = "cp.hourly_rate >= ?";
            $params[] = 200;
        }
    }

    // Filter by language
    if (!empty($answers['preferred_language'])) {
        $where[] = "JSON_CONTAINS(cp.languages, ?)";
        $params[] = json_encode($answers['preferred_language']);
    }

    $whereClause = implode(' AND ', $where);

    // Get matching coaches
    $query = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.profile_image,
            cp.bio,
            cp.tagline,
            cp.specializations,
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
            cp.is_verified
        FROM users u
        INNER JOIN coach_profiles cp ON u.id = cp.user_id
        WHERE $whereClause
        ORDER BY cp.average_rating DESC, cp.total_reviews DESC
        LIMIT 20
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $coaches = $stmt->fetchAll();

    // Calculate match score for each coach
    foreach ($coaches as &$coach) {
        $coach['specializations'] = json_decode($coach['specializations'] ?? '[]', true);
        $matchScore = 100;

        // Calculate distance if location provided
        if (!empty($answers['location']) && $coach['location_lat'] && $coach['location_lng']) {
            // This is simplified - in production, you'd geocode the user's location first
            // For now, we'll just note that they both have locations
            $coach['has_location_match'] = true;
        }

        // Bonus points for verified coaches
        if ($coach['is_verified']) {
            $matchScore += 10;
        }

        // Bonus points for high ratings
        if ($coach['average_rating'] >= 4.5) {
            $matchScore += 10;
        }

        // Bonus points for experience
        if ($coach['years_experience'] >= 5) {
            $matchScore += 5;
        }

        $coach['match_score'] = min(100, $matchScore);
    }

    // Sort by match score
    usort($coaches, function($a, $b) {
        return $b['match_score'] - $a['match_score'];
    });

    return $coaches;
}

// If no route matched
sendError('Invalid endpoint or method', 404);
