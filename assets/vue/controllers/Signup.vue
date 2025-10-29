<script>
export default {
    data() {
        return {
            title: 'Sign up to Cyclops',
            email: '',
            password: '',
            confirmPassword: '',
            emailValid: false,
            emailTouched: false,
            passwordsMatch: false,
            confirmTouched: false,
            successMessage: '',
            errorMessage: '',
            showPasswordConditions: false,
            csrfToken: '',
            isLoading: false,
            signupAttempts: 0,
            lastSignupAttempt: null,
            isBlocked: false
        }
    },
    computed: {
        passwordLengthValid() {
            return this.password.length >= 8;
        },
        passwordMaxLengthValid() {
            return this.password.length <= 128;
        },
        passwordNumberValid() {
            return /\d/.test(this.password);
        },
        passwordUpperValid() {
            return /[A-Z]/.test(this.password);
        },
        passwordSpecialValid() {
            return /[^A-Za-z0-9]/.test(this.password);
        },
        passwordLowerValid() {
            return /[a-z]/.test(this.password);
        },
        formValid() {
            return this.emailValid &&
                this.passwordLengthValid &&
                this.passwordMaxLengthValid &&
                this.passwordNumberValid &&
                this.passwordUpperValid &&
                this.passwordLowerValid &&
                this.passwordSpecialValid &&
                this.passwordsMatch &&
                !this.isBlocked;
        }
    },
    async mounted() {
        await this.fetchCSRFToken();
        this.checkRateLimit();
    },
    methods: {
        /**
         * Fetch CSRF token from server
         */
        async fetchCSRFToken() {
            try {
                const response = await fetch('/csrf-token', {
                    method: 'GET',
                    credentials: 'same-origin'
                });
                if (response.ok) {
                    const data = await response.json();
                    this.csrfToken = data.token;
                }
            } catch (error) {
                console.error('Failed to fetch CSRF token:', error);
            }
        },

        /**
         * Check rate limiting for signup attempts
         */
        checkRateLimit() {
            const storedAttempts = localStorage.getItem('signupAttempts');
            const storedLastAttempt = localStorage.getItem('lastSignupAttempt');

            if (storedAttempts && storedLastAttempt) {
                this.signupAttempts = parseInt(storedAttempts);
                this.lastSignupAttempt = parseInt(storedLastAttempt);

                const now = Date.now();
                const BLOCK_DURATION = 30 * 60 * 1000; // 30 minutes for signup

                if (this.signupAttempts >= 3) {
                    if (now - this.lastSignupAttempt < BLOCK_DURATION) {
                        this.isBlocked = true;
                        const remainingTime = Math.ceil((BLOCK_DURATION - (now - this.lastSignupAttempt)) / 60000);
                        this.errorMessage = `Too many signup attempts. Please try again in ${remainingTime} minutes.`;
                    } else {
                        this.resetRateLimit();
                    }
                }
            }
        },

        /**
         * Reset rate limiting data
         */
        resetRateLimit() {
            this.signupAttempts = 0;
            this.lastSignupAttempt = null;
            this.isBlocked = false;
            localStorage.removeItem('signupAttempts');
            localStorage.removeItem('lastSignupAttempt');
        },

        /**
         * Enhanced email validation
         */
        validateEmail() {
            this.emailTouched = true;
            // More robust email validation
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

            // Check for common email issues
            const hasConsecutiveDots = /\.\./.test(this.email);
            const startsWithDot = this.email.startsWith('.');
            const endsWithDot = this.email.endsWith('.');
            const tooLong = this.email.length > 254;

            this.emailValid = emailRegex.test(this.email) &&
                             !hasConsecutiveDots &&
                             !startsWithDot &&
                             !endsWithDot &&
                             !tooLong;
        },

        /**
         * Sanitize input to prevent XSS
         */
        sanitizeInput(input) {
            if (typeof input !== 'string') return '';
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
        },

        /**
         * Validate password strength more thoroughly
         */
        validatePassword() {
            // Additional checks for common weak passwords
            const commonPasswords = ['password', '123456', 'qwerty', 'admin'];
            const isCommon = commonPasswords.some(common =>
                this.password.toLowerCase().includes(common)
            );

            if (isCommon) {
                this.errorMessage = 'The password is too common. Please choose a stronger password.';
                return false;
            }

            // Check for repeated characters
            const hasRepeatedChars = /(.)\1{2,}/.test(this.password);
            if (hasRepeatedChars) {
                this.errorMessage = 'The password contains repeated characters. Please choose a stronger password.';
                return false;
            }

            return true;
        },

        validatePasswordMatch() {
            this.confirmTouched = true;
            this.passwordsMatch = this.password === this.confirmPassword && this.password.length > 0;
        },

        async handleSubmit() {
            if (!this.formValid || this.isBlocked) return;

            this.errorMessage = '';
            this.successMessage = '';

            // Additional password validation
            if (!this.validatePassword()) {
                return;
            }

            this.isLoading = true;

            try {
                const response = await fetch('/api/signup', {
                    method: 'POST',
                    headers: {
                        "Content-Type": "application/json",
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': this.csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        email: this.sanitizeInput(this.email),
                        password: this.password,
                        confirmPassword: this.confirmPassword,
                        _token: this.csrfToken
                    })
                });

                if (response.ok) {
                    this.resetRateLimit();
                    this.successMessage = "Signup successful! Please check your email to verify your account.";
                    // Clear form for security
                    this.email = '';
                    this.password = '';
                    this.confirmPassword = '';
                } else {
                    // Increment failed attempts
                    this.signupAttempts++;
                    this.lastSignupAttempt = Date.now();

                    localStorage.setItem('signupAttempts', this.signupAttempts.toString());
                    localStorage.setItem('lastSignupAttempt', this.lastSignupAttempt.toString());

                    if (this.signupAttempts >= 3) {
                        this.isBlocked = true;
                        this.errorMessage = 'Too many signup attempts. Please try again in 30 minutes.';
                    } else {
                        const remainingAttempts = 3 - this.signupAttempts;
                        this.errorMessage = `Error during signup. You have ${remainingAttempts} attempts left.`;
                    }
                }
            } catch (error) {
                console.error('Signup error:', error);
                this.errorMessage = 'An unexpected error occurred. Please try again later.';
            } finally {
                this.isLoading = false;
            }
        }
    }
}
</script>

<template>
    <div id="app">
        <div class="signup-container">
            <header class="header">
                <h1>{{ title }}</h1>
            </header>
            <div v-if="successMessage" class="alert alert-success">
                {{ successMessage }}
            </div>
            <div v-if="errorMessage" class="alert alert-error">
                {{ errorMessage }}
            </div>
            <form @submit.prevent="handleSubmit">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        v-model="email"
                        required
                        @blur="validateEmail"
                        :disabled="isLoading || isBlocked"
                        maxlength="254"
                        autocomplete="email"
                    />
                    <span v-if="emailTouched && !emailValid" class="validation-error">
                        Invalid email address
                    </span>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        v-model="password"
                        required
                        @focus="showPasswordConditions = true"
                        @blur="showPasswordConditions = false"
                        :disabled="isLoading || isBlocked"
                        maxlength="128"
                        autocomplete="new-password"
                    />
                    <!-- Conditions list -->
                    <ul v-if="showPasswordConditions" class="password-conditions">
                        <li :class="{valid: passwordLengthValid, invalid: !passwordLengthValid}">
                            At least 8 characters
                        </li>
                        <li :class="{valid: passwordMaxLengthValid, invalid: !passwordMaxLengthValid}">
                            At most 128 characters
                        </li>
                        <li :class="{valid: passwordNumberValid, invalid: !passwordNumberValid}">
                            At least one number
                        </li>
                        <li :class="{valid: passwordUpperValid, invalid: !passwordUpperValid}">
                            At least one uppercase letter
                        </li>
                        <li :class="{valid: passwordLowerValid, invalid: !passwordLowerValid}">
                            At least one lowercase letter
                        </li>
                        <li :class="{valid: passwordSpecialValid, invalid: !passwordSpecialValid}">
                            At least one special character (!@#$%^&*)
                        </li>
                    </ul>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <input
                        type="password"
                        id="confirmPassword"
                        v-model="confirmPassword"
                        required
                        @blur="validatePasswordMatch"
                        :disabled="isLoading || isBlocked"
                        maxlength="128"
                        autocomplete="new-password"
                    />
                    <span v-if="confirmTouched && !passwordsMatch" class="validation-error">
                        Passwords do not match
                    </span>
                </div>
                <button
                    type="submit"
                    class="submit-btn"
                    :disabled="!formValid || isLoading"
                >
                    {{ isLoading ? 'Inscription...' : 'Sign up' }}
                </button>
            </form>
            <p class="login-link">
                Already have an account? <a href="/login">Log in</a>
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
.form-group { margin-bottom: 1.25rem; }
label {
    display: block;
    margin-bottom: 0.3125rem;
    font-weight: bold;
    color: #333;
}
input[type="email"], input[type="password"] {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 0.3125rem;
    font-size: 1rem;
    box-sizing: border-box;
}
input[type="email"]:focus, input[type="password"]:focus{
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 0.125rem rgba(102, 126, 234, 0.2)
}
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
.login-link { text-align: center; margin-top: 1.25rem; color: #666; }
.login-link a { color: #667eea; text-decoration: none; }
.login-link a:hover { text-decoration: underline; }

.password-conditions {
    margin: 0.625rem 0 0;
    padding-left: 1.25rem;
    font-size: 0.9375rem;
}
.password-conditions li { margin-bottom: 0.375rem; }
.password-conditions .valid { color: #2e7d32; }
.password-conditions .invalid { color: #c62828; }
.validation-error { color: #c62828; font-size: 0.875rem; }

.mt-sm { margin-top: 0.625rem; }
.center { text-align: center; }

/* Responsive form tweaks */
@media (max-width: 36em) { /* <= 576px */
    .header { margin-bottom: 1.25rem; }
    input[type="email"], input[type="password"] { font-size: 0.9375rem; }
    .submit-btn { font-size: 1rem; }
}
</style>
