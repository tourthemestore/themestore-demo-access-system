/**
 * Video Tracking - ThemeStore Demo Access System
 * 
 * Tracks video start, progress (every 15 seconds), and completion
 * Sends AJAX POST requests with token and event type
 */

(function() {
    'use strict';
    
    // Configuration
    const PROGRESS_INTERVAL = 15; // Track progress every 15 seconds
    const TRACKING_ENDPOINT = '../api/track-video-activity.php';
    
    // State tracking
    let videoElement = null;
    let token = null;
    let lastTrackedProgress = 0;
    let hasTrackedStart = false;
    let hasTrackedCompletion = false;
    let progressTrackingInterval = null;
    
    /**
     * Get token from URL parameter
     */
    function getTokenFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('token') || null;
    }
    
    /**
     * Get token from video source URL
     */
    function getTokenFromVideoSource() {
        if (videoElement && videoElement.src) {
            try {
                const url = new URL(videoElement.src);
                return url.searchParams.get('token') || null;
            } catch (e) {
                return null;
            }
        }
        return null;
    }
    
    /**
     * Send tracking event to server
     */
    function trackEvent(eventType, progressPercentage = 0, durationWatched = 0) {
        if (!token) {
            console.warn('Token not available for tracking');
            return;
        }
        
        const data = {
            token: token,
            event_type: eventType,
            progress_percentage: Math.round(progressPercentage * 100) / 100,
            duration_watched: Math.round(durationWatched)
        };
        
        // Send AJAX POST request
        fetch(TRACKING_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                console.log('Video tracking event sent:', eventType, data);
            } else {
                console.warn('Video tracking failed:', result.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error tracking video event:', error);
        });
    }
    
    /**
     * Calculate progress percentage
     */
    function getProgressPercentage() {
        if (!videoElement || !videoElement.duration) {
            return 0;
        }
        return (videoElement.currentTime / videoElement.duration) * 100;
    }
    
    /**
     * Track video start
     */
    function trackStart() {
        if (hasTrackedStart) {
            return;
        }
        
        hasTrackedStart = true;
        trackEvent('started', 0, 0);
    }
    
    /**
     * Track video progress (every 15 seconds)
     */
    function trackProgress() {
        if (!videoElement || !videoElement.duration) {
            return;
        }
        
        const currentTime = Math.floor(videoElement.currentTime);
        const progressPercentage = getProgressPercentage();
        
        // Track if 15 seconds have passed since last tracking
        if (currentTime >= lastTrackedProgress + PROGRESS_INTERVAL) {
            lastTrackedProgress = Math.floor(currentTime / PROGRESS_INTERVAL) * PROGRESS_INTERVAL;
            trackEvent('progress', progressPercentage, currentTime);
        }
    }
    
    /**
     * Track video completion
     */
    function trackCompletion() {
        if (hasTrackedCompletion) {
            return;
        }
        
        hasTrackedCompletion = true;
        const progressPercentage = getProgressPercentage();
        const durationWatched = Math.floor(videoElement.currentTime || videoElement.duration);
        
        trackEvent('completed', progressPercentage, durationWatched);
        
        // Clear progress tracking interval
        if (progressTrackingInterval) {
            clearInterval(progressTrackingInterval);
            progressTrackingInterval = null;
        }
    }
    
    /**
     * Initialize video tracking
     */
    function initVideoTracking(videoId, providedToken) {
        // Get video element
        videoElement = document.getElementById(videoId);
        
        if (!videoElement) {
            console.error('Video element not found:', videoId);
            return;
        }
        
        // Get token
        token = providedToken || getTokenFromVideoSource() || getTokenFromURL();
        
        if (!token) {
            console.warn('Token not found. Video tracking will not work.');
            return;
        }
        
        // Track video start on play event
        videoElement.addEventListener('play', function() {
            trackStart();
            
            // Start progress tracking interval
            if (!progressTrackingInterval) {
                progressTrackingInterval = setInterval(trackProgress, 1000); // Check every second
            }
        }, { once: false });
        
        // Track progress on timeupdate (fires frequently)
        videoElement.addEventListener('timeupdate', function() {
            trackProgress();
        });
        
        // Track completion on ended event
        videoElement.addEventListener('ended', function() {
            trackCompletion();
        });
        
        // Track if video is paused/stopped before completion (abandoned)
        videoElement.addEventListener('pause', function() {
            // Only track as abandoned if video hasn't completed and has been watched for some time
            if (!hasTrackedCompletion && videoElement.currentTime > 5) {
                const progressPercentage = getProgressPercentage();
                const durationWatched = Math.floor(videoElement.currentTime);
                
                // Don't track abandoned if it's just a pause (user might resume)
                // We'll track this separately if needed
            }
        });
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (progressTrackingInterval) {
                clearInterval(progressTrackingInterval);
            }
        });
        
        console.log('Video tracking initialized for video:', videoId);
    }
    
    // Auto-initialize if video element exists with id 'demoVideo'
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            const video = document.getElementById('demoVideo');
            if (video) {
                const token = new URLSearchParams(window.location.search).get('token');
                initVideoTracking('demoVideo', token);
            }
        });
    } else {
        const video = document.getElementById('demoVideo');
        if (video) {
            const token = new URLSearchParams(window.location.search).get('token');
            initVideoTracking('demoVideo', token);
        }
    }
    
    // Export function for manual initialization
    window.initVideoTracking = initVideoTracking;
    
})();

