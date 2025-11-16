/**
 * CoachSearching API Client
 * Handles all communication with the PHP backend API
 */

const API_BASE_URL = 'https://clouedo.com/coachsearching/api';

class CoachSearchingAPI {
    constructor() {
        this.token = localStorage.getItem('auth_token');
        this.user = null;

        // Load user from localStorage if token exists
        if (this.token) {
            const storedUser = localStorage.getItem('user');
            if (storedUser) {
                this.user = JSON.parse(storedUser);
            }
        }
    }

    async request(endpoint, options = {}) {
        const url = `${API_BASE_URL}/${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        const config = {
            ...options,
            headers
        };

        if (options.body && typeof options.body === 'object') {
            config.body = JSON.stringify(options.body);
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Request failed');
            }

            return data;
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    // Authentication
    async register(userData) {
        const data = await this.request('auth/register', {
            method: 'POST',
            body: userData
        });
        this.setAuth(data.token, data.user);
        return data;
    }

    async login(email, password) {
        const data = await this.request('auth/login', {
            method: 'POST',
            body: { email, password }
        });
        this.setAuth(data.token, data.user);
        return data;
    }

    async logout() {
        try {
            await this.request('auth/logout', { method: 'POST' });
        } finally {
            this.clearAuth();
        }
    }

    async getMe() {
        const data = await this.request('auth/me');
        this.user = data.user;
        localStorage.setItem('user', JSON.stringify(data.user));
        return data;
    }

    setAuth(token, user) {
        this.token = token;
        this.user = user;
        localStorage.setItem('auth_token', token);
        localStorage.setItem('user', JSON.stringify(user));
    }

    clearAuth() {
        this.token = null;
        this.user = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
    }

    isAuthenticated() {
        return !!this.token;
    }

    getCurrentUser() {
        return this.user;
    }

    // Coaches
    async getCoaches(params = {}) {
        const query = new URLSearchParams(params).toString();
        return await this.request(`coaches?${query}`);
    }

    async getCoachProfile(coachId) {
        return await this.request(`coaches/profile?id=${coachId}`);
    }

    async getCoachSessions(coachId) {
        return await this.request(`coaches/sessions?coach_id=${coachId}`);
    }

    async getCoachArticles(coachId) {
        return await this.request(`coaches/articles?coach_id=${coachId}`);
    }

    async getArticle(articleId) {
        return await this.request(`coaches/articles?id=${articleId}`);
    }

    // Questionnaire
    async getQuestionnaire(userType = 'guest') {
        return await this.request(`questionnaire?user_type=${userType}`);
    }

    async submitQuestionnaire(answers) {
        return await this.request('questionnaire/submit', {
            method: 'POST',
            body: { answers }
        });
    }

    // Bookings
    async getBookings(status = '') {
        const query = status ? `?status=${status}` : '';
        return await this.request(`bookings${query}`);
    }

    async createBooking(bookingData) {
        return await this.request('bookings/create', {
            method: 'POST',
            body: bookingData
        });
    }

    async confirmBooking(bookingId, confirmedDatetime = null) {
        return await this.request('bookings/confirm', {
            method: 'POST',
            body: { booking_id: bookingId, confirmed_datetime: confirmedDatetime }
        });
    }

    async cancelBooking(bookingId, reason = null) {
        return await this.request('bookings/cancel', {
            method: 'POST',
            body: { booking_id: bookingId, reason }
        });
    }

    // Reviews
    async getReviews(coachId, page = 1) {
        return await this.request(`reviews?coach_id=${coachId}&page=${page}`);
    }

    async createReview(reviewData) {
        return await this.request('reviews/create', {
            method: 'POST',
            body: reviewData
        });
    }

    // Messages
    async getConversations() {
        return await this.request('messages/conversations');
    }

    async getMessages(conversationId = null, userId = null) {
        const query = conversationId ? `?conversation_id=${conversationId}` : `?user_id=${userId}`;
        return await this.request(`messages${query}`);
    }

    async sendMessage(toUserId, message) {
        return await this.request('messages/send', {
            method: 'POST',
            body: { to_user_id: toUserId, message }
        });
    }

    // User
    async getUserProfile() {
        return await this.request('user/profile');
    }

    async updateUserProfile(profileData) {
        return await this.request('user/profile', {
            method: 'PUT',
            body: profileData
        });
    }

    async followCoach(coachId) {
        return await this.request('user/follow', {
            method: 'POST',
            body: { coach_id: coachId }
        });
    }

    async unfollowCoach(coachId) {
        return await this.request('user/unfollow', {
            method: 'DELETE',
            body: { coach_id: coachId }
        });
    }

    async getUserFeed(page = 1) {
        return await this.request(`user/feed?page=${page}`);
    }

    async getUserBookings() {
        return await this.request('user/bookings');
    }

    // Business
    async getBusinessProfile() {
        return await this.request('business/profile');
    }

    async updateBusinessProfile(profileData) {
        return await this.request('business/profile', {
            method: 'PUT',
            body: profileData
        });
    }

    async inviteTeamMember(email) {
        return await this.request('business/invite', {
            method: 'POST',
            body: { email }
        });
    }

    async getTeamMembers() {
        return await this.request('business/team');
    }

    // Coach
    async getCoachDashboard() {
        return await this.request('coach/dashboard');
    }

    async getCoachOwnProfile() {
        return await this.request('coach/profile');
    }

    async updateCoachProfile(profileData) {
        return await this.request('coach/profile', {
            method: 'PUT',
            body: profileData
        });
    }

    async getCoachOwnSessions() {
        return await this.request('coach/sessions');
    }

    async createSession(sessionData) {
        return await this.request('coach/sessions', {
            method: 'POST',
            body: sessionData
        });
    }

    async getCoachAvailability() {
        return await this.request('coach/availability');
    }

    async setCoachAvailability(availabilityData) {
        return await this.request('coach/availability', {
            method: 'POST',
            body: availabilityData
        });
    }

    async getCoachBookings(status = '') {
        const query = status ? `?status=${status}` : '';
        return await this.request(`coach/bookings${query}`);
    }

    async getCoachOwnArticles() {
        return await this.request('coach/articles');
    }

    async createArticle(articleData) {
        return await this.request('coach/articles', {
            method: 'POST',
            body: articleData
        });
    }

    // Admin
    async getAdminStats() {
        return await this.request('admin/stats');
    }

    async getAdminUsers(params = {}) {
        const query = new URLSearchParams(params).toString();
        return await this.request(`admin/users?${query}`);
    }

    async updateUser(userId, userData) {
        return await this.request(`admin/users/${userId}`, {
            method: 'PUT',
            body: userData
        });
    }

    async getAdminCoaches(page = 1) {
        return await this.request(`admin/coaches?page=${page}`);
    }

    async updateCoach(coachId, coachData) {
        return await this.request(`admin/coaches/${coachId}`, {
            method: 'PUT',
            body: coachData
        });
    }

    async getAdminBookings(page = 1) {
        return await this.request(`admin/bookings?page=${page}`);
    }

    async getAdminReviews(page = 1) {
        return await this.request(`admin/reviews?page=${page}`);
    }

    async updateReview(reviewId, reviewData) {
        return await this.request(`admin/reviews/${reviewId}`, {
            method: 'PUT',
            body: reviewData
        });
    }

    async getAdminDelegates() {
        return await this.request('admin/delegates');
    }

    async assignDelegate(userId) {
        return await this.request('admin/delegates', {
            method: 'POST',
            body: { user_id: userId }
        });
    }

    async removeDelegate(delegateUserId) {
        return await this.request(`admin/delegates/${delegateUserId}`, {
            method: 'DELETE'
        });
    }

    // Notifications
    async getNotifications(page = 1) {
        return await this.request(`notifications?page=${page}`);
    }

    async markNotificationsRead(notificationId = null) {
        const body = notificationId ? { notification_id: notificationId } : {};
        return await this.request('notifications/mark-read', {
            method: 'PUT',
            body
        });
    }

    // Upload
    async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);

        const headers = {};
        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        const response = await fetch(`${API_BASE_URL}/upload`, {
            method: 'POST',
            headers,
            body: formData
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Upload failed');
        }

        return data;
    }

    // Payments
    async createPaymentIntent(bookingId) {
        return await this.request('payments/create-intent', {
            method: 'POST',
            body: { booking_id: bookingId }
        });
    }
}

// Create global API instance
const api = new CoachSearchingAPI();
