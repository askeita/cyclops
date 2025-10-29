<script setup>
import loginIcon from '/assets/images/login.svg';
import targetIcon from '/assets/images/target.svg';
import bookIcon from '/assets/images/book.svg';
import apiIcon from '/assets/images/api.svg';
import lockIcon from '/assets/images/lock.svg';
import checkIcon from '/assets/images/check.svg';


const title = 'Cyclops API';
const projectDescription = 'The Cyclops API provides access to a comprehensive database of historical economic and financial crises, enabling users to retrieve detailed information about various crises, including their characteristics, impacts, and timelines.';
const apiDocsUrl = '/api/docs';
const crisisProperties = [
    {name: 'id', description: 'Unique identifier for the crisis'},
    {name: 'name', description: 'Name of the crisis'},
    {name: 'type', description: 'Type of crisis (e.g., banking, currency, debt)'},
    {name: 'start_year', description: 'Year the crisis started'},
    {name: 'end_year', description: 'Year the crisis ended'},
    {name: 'countries_affected', description: 'List of countries affected by the crisis'},
    {name: 'description', description: 'Detailed description of the crisis'},
    {name: 'impact', description: 'Economic impact of the crisis (e.g., GDP decline, unemployment rate)'}
]
</script>

<template>
    <div id="app">
        <div class="container">
            <header class="header">
                <div class="header-content">
                    <div class="header-text">
                        <h1>{{ title }}</h1>
                        <p>API for accessing data on economic and financial crises.</p>
                    </div>
                    <div class="login-icon">
                        <a href="/login" class="login-link">
                            <img :src="loginIcon" alt="login"/>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Section 1: Project description -->
            <section class="section">
                <h2>
                    <img :src="targetIcon" alt="target" class="section-icon"/>
                    Project Objective
                </h2>
                <p>{{ projectDescription }}</p>
                <ul>
                    <li>Centralized access to historical economic and financial crisis data</li>
                    <li>Comparative analysis of different types of crises</li>
                    <li>Documented RESTful API interface</li>
                    <li>Structured data for academic research and analysis</li>
                </ul>
            </section>

            <!-- Section 2: API documentation -->
            <section class="section">
                <h2>
                    <img :src="bookIcon" alt="book" class="section-icon"/>
                    API Documentation
                </h2>
                <p>Explore our interactive API and discover all available endpoints: </p>
                <a :href="apiDocsUrl" class="api-link" target="_self">
                    <img :src="apiIcon" alt="api" class="section-icon"/>
                    <span class="access-api-doc">Access the Swagger UI documentation</span>
                </a>
                <h3>Characteristics of crises</h3>
                <p>Each crisis is described by the following properties:</p>
                <div class="property-grid">
                    <div class="property-item" v-for="property in crisisProperties" :key="property.name">
                        <strong>{{ property.name }}</strong>
                        <p>{{ property.description }}</p>
                    </div>
                </div>
            </section>

            <!-- Section 3: Authentification -->
            <section class="section">
                <h2>
                    <img :src="lockIcon" alt="lock" class="section-icon"/>
                    API Access
                </h2>
                <div class="box-info">
                    <h3>
                        <img :src="checkIcon" alt="check" class="section-icon"/>
                        Free and Open source API
                    </h3>
                    <p>To access the data, you need to register and obtain an API key</p>
                    <p><strong>Steps to get started:</strong></p>
                    <ol>
                        <li>Sign up <a href="/signup">here</a> to create your account</li>
                        <li>Get your personal API key</li>
                        <li>Use your key in your queries (header <code>X-API-KEY</code>)
                        </li>
                    </ol>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.container {
    max-width: 75rem;
    margin: 0 auto;
    padding: 1.25rem;
}

/**********
 * Header *
 **********/
.header {
    text-align: center;
    color: white;
    margin-bottom: 2.5rem; /* 40px */
}
.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    gap: 1rem;
}
.header-text { flex: 1; }

/*********
 * Icons *
 *********/
.section-icon { width: 1.625rem; height: 1.625rem; vertical-align: middle; }
.login-icon { margin-left: 1.25rem; }
.login-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    color: white;
    text-decoration: none;
    transition: background 0.3s, transform 0.2s;
}
.login-link:hover { background: rgba(255, 255, 255, 0.3); transform: scale(1.1); }
.login-link svg { width: 1.25rem; height: 1.25rem; }

.section {
    background: white;
    margin: 1.25rem 0;
    padding: 1.875rem;
    border-radius: 0.625rem;
    box-shadow: 0 0.25rem 0.375rem rgba(0, 0, 0, 0.1);
}
.api-link {
    display: inline-block;
    background: #667eea;
    color: white;
    padding: 0.75rem 0.625rem;
    text-decoration: none;
    border-radius: 0.3125rem;
    margin: 0.625rem 0;
    transition: background 0.3s;
}
.api-link:hover { background: #5a6fd8; }
.access-api-doc { margin-left: 0.625rem; vertical-align: middle; }

.property-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(15.625rem, 1fr));
    gap: 0.9375rem;
    margin-top: 1.25rem;
}
.property-item {
    padding: 0.625rem;
    background: #f8f9fa;
    border-left: 0.25rem solid #667eea;
    border-radius: 0.1875rem;
}

.box-info {
    background: #e8f5e8;
    padding: 1.25rem;
    border-radius: 0.3125rem;
    margin: 0.9375rem 0;
}

/* Responsive adjustments */
@media (max-width: 48em) { /* <= 768px */
    .container {
        padding: 1rem;
    }
    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .section {
        padding: 1.25rem;
    }
}
@media (max-width: 36em) { /* <= 576px */
    .container {
        padding: 0.75rem;
    }
    .property-grid {
        grid-template-columns: 1fr;
    }
    .login-link {
        width: 2.25rem; height: 2.25rem;
    }
}
</style>
