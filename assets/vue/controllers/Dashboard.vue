<script setup>
import {ref, onMounted} from "vue";
import drawerIcon from '/assets/images/drawer.svg';
import homeIcon from '/assets/images/home.svg';
import historyIcon from '/assets/images/history.svg';
import logoutIcon from '/assets/images/logout.svg';
import copyIcon from '/assets/images/copy.svg';
import chainIcon from '/assets/images/chain.svg';


// Vue reactive state variables
const userEmail = ref('');
const apiKey = ref('');
const isGenerating = ref(false);
const lastConnection = ref('');
const showCopySuccess = ref(false);
const copyMessage = ref('');

let hasApiKey = ref(false);
let drawerOpen = ref(false);
let currentView = ref('home');


/**
 * Format date from '2025-10-20 20:05:26' to 'Monday, October 20th at 10:08 PM'
 */
const formatLastConnection = (dateString) => {
    if (!dateString) return '';

    try {
        const date = new Date(dateString);

        // Get day of week
        const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const dayOfWeek = dayNames[date.getDay()];

        // Get month name
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        const month = monthNames[date.getMonth()];

        // Get day with ordinal suffix (1st, 2nd, 3rd, 4th, etc.)
        const day = date.getDate();
        const getOrdinalSuffix = (n) => {
            const s = ['th', 'st', 'nd', 'rd'];
            const v = n % 100;
            return n + (s[(v - 20) % 10] || s[v] || s[0]);
        };
        const dayWithSuffix = getOrdinalSuffix(day);

        // Get time in 12-hour format
        const timeString = date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });

        return `${dayOfWeek}, ${month} ${dayWithSuffix} at ${timeString}`;
    } catch (error) {
        console.error('Error formatting date:', error);
        return dateString; // fallback to original format
    }
};

/**
 * On component mount, fetch user data from the backend
 */
onMounted(() => {
    const dashboardData = window.dashboardData || {};

    userEmail.value = dashboardData.userEmail || '';
    apiKey.value = dashboardData.userApiKey || '';
    lastConnection.value = dashboardData.lastConnection || '';
    hasApiKey.value = !!dashboardData.userApiKey;
})

/**
 * Toggle the drawer open/close state
 */
const toggleDrawer = () => {
    drawerOpen.value = !drawerOpen.value;
}

/**
 * Navigate to Home view
 */
const goToHome = () => {
    currentView.value = 'home';
}

/**
 * Navigate to History view
 */
const goToHistory = () => {
    currentView.value = 'history';
}

/**
 * Generate a new API key by calling the backend endpoint
 */
async function generateApiKey() {
    isGenerating.value = true;
    try {
        const response = await fetch('/dashboard/generate-api-key', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        });

        if (response.ok) {
            apiKey.value = await response.text();
            hasApiKey.value = true;
        } else {
            alert('Error during API key generation');
        }
    } catch (error) {
        alert('Network error during API key generation');
    } finally {
        isGenerating.value = false;
    }
}

/**
 * Get a masked version of the API key for display
 */
function getMaskedApiKey() {
    if (!apiKey.value) return 'No API key set';

    return apiKey.value.substring(0, 6) + '*'.repeat(apiKey.value.length - 6);
}

/**
 * Copy the API key to clipboard
 */
function copyApiKey(text) {
    console.log("135: " + JSON.stringify(text));
    navigator.clipboard.writeText(apiKey.value).then(() => {
        copyMessage.value = `API Key copied to clipboard!`;
        showCopySuccess.value = true;

        // Hide the message after 3 seconds
        setTimeout(() => {
            showCopySuccess.value = false;
        }, 3000);
    }).catch(err => {
        console.error('Failed to copy API key: ', err);
        copyMessage.value = 'Failed to copy API key to clipboard.';
        showCopySuccess.value = true;

        setTimeout(() => {
            showCopySuccess.value = false;
        }, 3000);
    });
}

/**
 * Access the API via Swagger UI, securely passing the API key
 */
async function accessApi() {
    try {
        const r = await fetch('/api/docs/auth', {
            method: 'POST',
            headers: { 'X-API-KEY': apiKey.value },
            credentials: 'same-origin'
        });
        if (!r.ok) {
            alert('Authentication failed. Cannot access API docs.');
            return;
        }
        window.open('/api/docs', '_blank', 'noopener');
    } catch (e) {
        alert('Error accessing API docs.');
    }
}
</script>

<template>
    <div class="dashboard">
        <aside :class="{ 'drawer': true, 'drawer-open': drawerOpen }">
            <button @click="toggleDrawer" class="drawer-toggle">
                <img :src="drawerIcon" alt="toggle drawer"/>
            </button>
            <nav>
                <ul>
                    <li @click="goToHome" :class="{active: currentView === 'home'}">
                        <img :src="homeIcon" class="icon" alt="home"/>
                        <span class="text">Home</span>
                    </li>
                    <li @click="goToHistory" :class="{ active: currentView === 'history' }">
                        <img :src="historyIcon" class="icon" alt="history"/>
                        <span class="text">History</span>
                    </li>
                    <li class="sidebar-footer">
                        <a href="/logout" class="logout-btn">
                            <img :src="logoutIcon" alt="logout"/>
                            <span class="text">Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        <main class="content">
            <div v-if="currentView === 'home'" class="home-view">
                <div v-if="showCopySuccess" class="alert alert-success api-key-copy">
                    {{ copyMessage }}
                </div>
                <h1>Welcome, {{ userEmail }}</h1>
                <!-- API Key Section: no key found -->
                <div v-if="!hasApiKey" class="no-api-key">
                    <button
                        @click="generateApiKey"
                        :disabled="isGenerating"
                        class="request-btn"
                    >
                        {{ isGenerating ? 'Generation...' : 'Request API Key' }}
                    </button>
                    <input
                        type="text"
                        :value="getMaskedApiKey()"
                        readonly
                        class="api-key-input"
                    />
                </div>
                <!-- Display API Key Section -->
                <div v-else class="api-key-display">

                    <div class="api-key-controls">
                        <button class="valid-key-btn">
                            Your valid API key
                        </button>
                        <input
                            type="text"
                            :value="getMaskedApiKey()"
                            readonly
                            class="api-key-input"
                        />
                        <button @click="copyApiKey" class="copy-btn" title="Copy API Key">
                            <img :src="copyIcon" alt="copy" class="copy-icon"/>
                        </button>
                        <button @click="accessApi" class="api-link" title="Access the API">
                            <img :src="chainIcon" class="chain-icon" alt="chain"/> API
                        </button>
                    </div>
                </div>
            </div>
            <div v-if="currentView === 'history'" class="history-view">
                <h2>API Request History</h2>
            </div>
            <p class="last-connection">Last connection: {{ formatLastConnection(lastConnection) }}</p>
        </main>
    </div>
</template>

<style scoped>
.dashboard {
    display: flex;
    min-height: 100vh;
    width: 100vw;
    background: #f5f5f5;
    position: fixed;
    top: 0;
    left: 0;
    margin: 0;
    padding: 0;
    align-items: stretch;
    justify-content: flex-start;
}

h2 { color: #232946; margin-bottom: 1rem; }

/**********
 * Drawer *
 **********/
.drawer {
    width: 5rem; /* 80px */
    background: #232946;
    color: #FFF;
    transition: width 0.3s ease;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
}

.drawer.drawer-open { width: 13.75rem; }

.drawer-toggle {
    background: none;
    border: none;
    color: #FFF;
    font-size: 2rem;
    margin: 1rem;
    cursor: pointer;
    width: calc(100% - 2rem);
    text-align: left;
    padding-left: 0.5rem;
}

.drawer-toggle img { width: 1.5rem; height: 1.5rem; background-color: #FFF; }

.drawer nav { margin-top: 2rem; }

.drawer ul { list-style: none; padding: 0; margin: 0; }

.drawer li {
    display: flex;
    align-items: center;
    padding: 1rem;
    cursor: pointer;
    transition: background 0.2s;
    white-space: nowrap;
}
.drawer li .icon {
    font-size: 1.5rem;
    width: 2rem;
    text-align: center;
    flex-shrink: 0;
}
.drawer li .text {
    margin-left: 1rem;
    opacity: 0;
    transition: opacity 0.3s ease;
    visibility: hidden;
}
.drawer.drawer-open li .text {
    opacity: 1;
    visibility: visible;
}
.drawer li.active, .drawer li:hover {
    background: #394867;
}

/* logout button */
.sidebar-footer {
    margin-top: auto;
    padding: 1rem;
    border-top: 1px solid #394867;
}
.logout-btn {
    display: flex;
    align-items: center;
    color: #FFF;
    text-decoration: none;
    padding: 0.5rem;
    border-radius: 0.25rem; /* 4px */
    transition: background-color 0.2s;
    white-space: nowrap;
}
.logout-btn:hover {
    background-color: rgba(220, 53, 69, 0.1);
}
.logout-btn img {
    flex-shrink: 0;
    background-color: #FFF;
    width: 1.5rem;
    height: 1.5rem;
}
.logout-btn .text {
    margin-left: 1rem;
    opacity: 0;
    transition: opacity 0.3s ease;
    visibility: hidden;
}
.drawer .drawer-open .logout-btn .text {
    opacity: 1;
    visibility: visible;
}

/*******************
 * API Key Section *
 *******************/
.content {
    flex: 1;
    padding: 2rem 3rem;
    overflow-y: auto;
    height: 100vh;
}
.api-key-display {
    margin-top: 1rem;
}
.api-key-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.valid-key-btn {
    background: #2e7d32;
    color: #fff;
    border: none;
    padding: 0.5rem 0.75rem;
    border-radius: 0.25rem;
}
.api-key-input {
    flex: 1;
    min-width: 12rem;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 0.25rem;
}
.copy-btn, .api-link {
    background: #667eea;
    color: #fff;
    border: none;
    padding: 0.5rem 0.75rem;
    border-radius: 0.25rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.copy-btn:hover, .api-link:hover {
    background: #5a6fd8;
}
.copy-icon, .chain-icon {
    width: 1rem; height: 1rem;
}
.api-key-copy {
    margin-bottom: 1rem;
}
.last-connection {
    margin-top: 2rem;
    color: #666;
}

/* Responsive adjustments */
@media (max-width: 48em) { /* <= 768px */
    .drawer {
        position: sticky;
        top: 0;
        height: 100vh;
    }
    .content {
        padding: 1.5rem;
    }
}
@media (max-width: 36em) { /* <= 576px */
    .drawer {
        width: 4.5rem;
    }
    .drawer.drawer-open {
        width: 12rem;
    }
    .drawer-toggle {
        font-size: 1.5rem;
    }
    .content {
        padding: 1rem;
    }
}
</style>
