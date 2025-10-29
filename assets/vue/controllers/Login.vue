<script setup>
import { ref, onMounted } from 'vue';
import eyeOpenIcon from '/assets/images/eye-open.svg';
import eyeClosedIcon from '/assets/images/eye-closed.svg';


// Component props
const props = defineProps({
    emailVerified: {
        type: String,
        default: null
    }
});

const title = 'Login to your account';
const email = ref('');
const password = ref('');
const showPassword = ref(false);
const csrfToken = ref('');
const isLoading = ref(false);
const errorMessage = ref('');
const loginAttempts = ref(0);
const lastAttemptTime = ref(null);
const isBlocked = ref(false);
const successMessage = ref('');

// Rate limiting configuration
const MAX_ATTEMPTS = 5;
const BLOCK_DURATION = 15 * 60 * 1000; // 15 minutes

onMounted(async () => {
    // Fetch CSRF token on component mount
    await fetchCSRFToken();
    checkRateLimit();

    // Show success message if email was verified
    if (props.emailVerified === 'true') {
        successMessage.value = 'Email successfully verified. You can now log in.';
    }
});

/**
 * Fetch CSRF token from server
 */
async function fetchCSRFToken() {
    try {
        const response = await fetch('/csrf-token', {
            method: 'GET',
            credentials: 'same-origin'
        });
        if (response.ok) {
            const data = await response.json();
            csrfToken.value = data.token;
        }
    } catch (error) {
        console.error('Failed to fetch CSRF token:', error);
    }
}

/**
 * Check rate limiting from localStorage
 */
function checkRateLimit() {
    const storedAttempts = localStorage.getItem('loginAttempts');
    const storedLastAttempt = localStorage.getItem('lastLoginAttempt');

    if (storedAttempts && storedLastAttempt) {
        loginAttempts.value = parseInt(storedAttempts);
        lastAttemptTime.value = parseInt(storedLastAttempt);

        const now = Date.now();
        if (loginAttempts.value >= MAX_ATTEMPTS) {
            if (now - lastAttemptTime.value < BLOCK_DURATION) {
                isBlocked.value = true;
                const remainingTime = Math.ceil((BLOCK_DURATION - (now - lastAttemptTime.value)) / 60000);
                errorMessage.value = `Too many login attempts. Please try again in ${remainingTime} minutes.`;
            } else {
                // Reset after block duration
                resetRateLimit();
            }
        }
    }
}

/**
 * Reset rate limiting data
 */
function resetRateLimit() {
    loginAttempts.value = 0;
    lastAttemptTime.value = null;
    isBlocked.value = false;
    localStorage.removeItem('loginAttempts');
    localStorage.removeItem('lastLoginAttempt');
}

/**
 * Validate email format
 */
function validateEmail(email) {
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    return emailRegex.test(email) && email.length <= 254;
}

/**
 * Sanitize input to prevent XSS
 */
function sanitizeInput(input) {
    return input.replace(/[<>&"']/g, function(match) {
        const escape = {
            '<': '&lt;',
            '>': '&gt;',
            '&': '&amp;',
            '"': '&quot;',
            "'": '&#x27;'
        };
        return escape[match];
    });
}

/**
 * State variable to track password visibility.
 */
const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
}

/**
 * Handles the submission of the login form.
 */
async function handleSubmit() {
    if (isBlocked.value) {
        return;
    }

    errorMessage.value = '';

    // Validate inputs
    if (!validateEmail(email.value)) {
        errorMessage.value = 'Invalid email format.';
        return;
    }

    if (password.value.length < 8 || password.value.length > 128) {
        errorMessage.value = 'The password must be between 8 and 128 characters long.';
        return;
    }

    isLoading.value = true;

    try {
        const response = await fetch('/api/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken.value
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                email: sanitizeInput(email.value),
                password: password.value,
                _token: csrfToken.value
            })
        });

        if (response.ok) {
            // Reset rate limiting on successful login
            resetRateLimit();
            window.location.href = '/dashboard';
        } else {
            console.error('Login failed');
            // Increment failed attempts
            loginAttempts.value++;
            lastAttemptTime.value = Date.now();

            localStorage.setItem('loginAttempts', loginAttempts.value.toString());
            localStorage.setItem('lastLoginAttempt', lastAttemptTime.value.toString());

            if (loginAttempts.value >= MAX_ATTEMPTS) {
                isBlocked.value = true;
                const remainingTime = Math.ceil(BLOCK_DURATION / 60000);
                errorMessage.value = `Too many login attempts. Please try again in ${remainingTime} minutes.`;
            } else {
                const remainingAttempts = MAX_ATTEMPTS - loginAttempts.value;
                errorMessage.value = `Invalid email or password. You have ${remainingAttempts} attempts left.`;
            }
        }
    } catch (error) {
        console.error('Login error:', error);
        errorMessage.value = 'A connection error occurred. Please try again later.';
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <div id="app">
        <div class="login-container">
            <header class="header">
                <h1>{{ title }}</h1>
            </header>

            <!-- Success and Error Messages -->
            <div v-if="successMessage" class="alert alert-success">
                {{ successMessage }}
            </div>

            <div v-if="errorMessage" class="alert alert-error">
                {{ errorMessage }}
            </div>

            <!-- Login form -->
            <form @submit.prevent="handleSubmit">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        v-model="email"
                        required
                        :disabled="isLoading || isBlocked"
                        maxlength="254"
                        autocomplete="email"
                    />
                </div>
                <div class="form-group password-group">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        v-model="password"
                        placeholder="Password"
                        class="password-input"
                        required
                        :disabled="isLoading || isBlocked"
                        maxlength="128"
                        autocomplete="current-password"
                    />
                    <button
                        type="button"
                        @click="togglePasswordVisibility"
                        class="password-toggle-btn"
                        :disabled="isLoading || isBlocked"
                    >
                        <img :src="showPassword ? eyeClosedIcon : eyeOpenIcon" alt="toggle password visibility"
                             class="eye-icon"/>
                    </button>
                </div>
                <button
                    type="submit"
                    class="submit-btn"
                    :disabled="isLoading || isBlocked"
                >
                    {{ isLoading ? 'Connexion...' : 'Login' }}
                </button>
            </form>
            <p class="login-link mt-md center">
                <a href="/signup">Don't have an account? Register</a>
            </p>
            <p class="mt-sm center">
                <a href="/">Back to Home</a>
            </p>
        </div>
    </div>
</template>

<style scoped>
.header {
    text-align: center;
    margin-bottom: 1.875rem;
    color: #333;
}

.form-group { margin-bottom: 1.25rem }

label {
    display: block;
    margin-bottom: 0.3125rem;
    font-weight: bold;
    color: #333;
}

/* Wrap to position password toggle without magic numbers */
.password-group { position: relative; }

input[type="email"], input[type="password"] {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 0.3125rem;
    font-size: 1rem;
    box-sizing: border-box;
}

input[type="email"]:focus, input[type="password"]:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 0.125rem rgba(102, 126, 234, 0.2)
}

/* Password input with toggle button */
.password-input { padding-right: 2.5rem; }
.password-toggle-btn {
    position: absolute;
    right: 0.5rem;
    top: 2.5rem;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.3125rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.eye-icon {
    width: 1.25rem;
    height: 1.25rem;
    color: #666;
}

/* Submit button */
.submit-btn {
    width: 100%;
    background: #667eea;
    color: white;
    padding: 0.75rem;
    border: none;
    border-radius: 0.3125rem;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.3s;
}

.submit-btn:hover { background: #5a6fd8 }

.login-link a { color: #667eea; text-decoration: none; }
.login-link a:hover { text-decoration: underline; }

/* Disabled state styles */
input:disabled, button:disabled { opacity: 0.6; cursor: not-allowed; }
.submit-btn:disabled { background: #ccc; cursor: not-allowed; }
.submit-btn:disabled:hover { background: #ccc; }

/* Margin utility classes */
.mt-md { margin-top: 1.25rem; }
.mt-sm { margin-top: 0.625rem; }

/* Center utility class */
.center { text-align: center; }

/* Responsive adjustments */
@media (max-width: 36em) { /* <= 576px */
    .header { margin-bottom: 1.25rem; }
    input[type="email"], input[type="password"], .password-input { font-size: 0.9375rem; }
    .password-toggle-btn { top: 2.25rem; }
}
@media (min-width: 64em) { /* >= 1024px */
    .header { margin-bottom: 2rem; }
    .submit-btn { font-size: 1.0625rem; }
}
</style>
