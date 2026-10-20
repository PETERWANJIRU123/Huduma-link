(function() {
    const chatbotToggle = document.getElementById('chatbotToggle');
    const chatbotWindow = document.getElementById('chatbotWindow');
    const chatbotClose = document.getElementById('chatbotClose');
    const chatbotInput = document.getElementById('chatbotInput');
    const chatbotSend = document.getElementById('chatbotSend');
    const chatbotMessages = document.getElementById('chatbotMessages');
    const chatbotQuickActions = document.getElementById('chatbotQuickActions');

    // Check if we're on an admin page (more reliable detection)
    const isAdminPage = window.location.pathname.replace(/\\/g, '/').includes('/admin/');

    // Navigation mappings
    const navigationMap = {
        'home': { url: isAdminPage ? '../index.php' : 'index.php', label: 'Home' },
        'services': { url: isAdminPage ? '../services.php' : 'services.php', label: 'Request a Service' },
        'track': { url: isAdminPage ? '../track.php' : 'track.php', label: 'Track Request' },
        'dashboard': { url: isAdminPage ? 'index.php' : 'dashboard.php', label: isAdminPage ? 'Staff Console' : 'Dashboard' },
        'requests': { url: 'requests.php', label: 'Requests Queue' },
        'users': { url: 'users.php', label: 'Users' },
        'login': { url: isAdminPage ? '../login.php' : 'login.php', label: 'Sign In' },
        'register': { url: isAdminPage ? '../register.php' : 'register.php', label: 'Create Account' },
        'logout': { url: isAdminPage ? '../logout.php' : 'logout.php', label: 'Sign Out' },
        'profile': { url: isAdminPage ? '../profile.php' : 'profile.php', label: 'Profile' },
        'notifications': { url: isAdminPage ? '../notifications.php' : 'notifications.php', label: 'Notifications' }
    };

    // Response patterns for admin pages
    const adminResponsePatterns = [
        {
            keywords: ['home', 'main', 'homepage', 'start'],
            response: 'I can take you to the home page.',
            action: 'home'
        },
        {
            keywords: ['dashboard', 'my desk', 'staff console', 'admin panel', 'console', 'main menu', 'staff', 'overview', 'summary'],
            response: 'I can take you to your dashboard.',
            action: 'dashboard'
        },
        {
            keywords: ['request', 'queue', 'incoming', 'list', 'all requests'],
            response: 'I can take you to the requests queue.',
            action: 'requests'
        },
        {
            keywords: ['user', 'users', 'people', 'accounts', 'staff', 'manage users'],
            response: 'I can take you to the users management page.',
            action: 'users'
        },
        {
            keywords: ['contact', 'address', 'phone', 'email', 'call', 'reach', 'office'],
            response: 'Here is our contact information:',
            action: 'contact'
        },
        {
            keywords: ['help', 'assist', 'support'],
            response: 'I can help you navigate to: Home, Dashboard, Requests, Users, or Contact Info. What would you like to do?',
            action: null
        },
        {
            keywords: ['thank', 'thanks'],
            response: 'You\'re welcome! Is there anything else I can help you with?',
            action: null
        },
        {
            keywords: ['bye', 'goodbye', 'exit'],
            response: 'Goodbye! Feel free to come back if you need help.',
            action: null
        }
    ];

    // Response patterns for citizen/visitor pages
    const publicResponsePatterns = [
        {
            keywords: ['home', 'main', 'homepage', 'start'],
            response: 'I can take you to the home page.',
            action: 'home'
        },
        {
            keywords: ['service', 'request', 'apply', 'submit', 'streetlight', 'water', 'garbage', 'bursary'],
            response: 'I can help you request a service. Would you like to go to the services page?',
            action: 'services'
        },
        {
            keywords: ['track', 'status', 'progress', 'check', 'follow'],
            response: 'I can help you track your request status.',
            action: 'track'
        },
        {
            keywords: ['dashboard', 'my desk', 'my requests', 'account'],
            response: 'I can take you to your dashboard.',
            action: 'dashboard'
        },
        {
            keywords: ['sign in', 'login', 'log in'],
            response: 'I can take you to the sign-in page.',
            action: 'login'
        },
        {
            keywords: ['register', 'sign up', 'create account', 'new account'],
            response: 'I can help you create a new account.',
            action: 'register'
        },
        {
            keywords: ['sign out', 'logout', 'log out'],
            response: 'I can help you sign out.',
            action: 'logout'
        },
        {
            keywords: ['profile', 'settings', 'details'],
            response: 'I can take you to your profile page.',
            action: 'profile'
        },
        {
            keywords: ['notification', 'alert', 'message'],
            response: 'I can take you to your notifications.',
            action: 'notifications'
        },
        {
            keywords: ['contact', 'address', 'phone', 'email', 'call', 'reach', 'office'],
            response: 'Here is our contact information:',
            action: 'contact'
        },
        {
            keywords: ['help', 'assist', 'support'],
            response: 'I can help you navigate to: Home, Request a Service, Track Request, Dashboard, Sign In, Create Account, or Contact Info. What would you like to do?',
            action: null
        },
        {
            keywords: ['thank', 'thanks'],
            response: 'You\'re welcome! Is there anything else I can help you with?',
            action: null
        },
        {
            keywords: ['bye', 'goodbye', 'exit'],
            response: 'Goodbye! Feel free to come back if you need help.',
            action: null
        }
    ];

    // Use appropriate response patterns based on page type
    const responsePatterns = isAdminPage ? adminResponsePatterns : publicResponsePatterns;

    // Toggle chatbot window
    chatbotToggle.addEventListener('click', () => {
        chatbotWindow.classList.toggle('active');
        if (chatbotWindow.classList.contains('active')) {
            chatbotInput.focus();
        }
    });

    // Close chatbot window
    chatbotClose.addEventListener('click', () => {
        chatbotWindow.classList.remove('active');
    });

    // Quick action buttons
    chatbotQuickActions.addEventListener('click', (e) => {
        if (e.target.classList.contains('chatbot-quick-btn')) {
            const action = e.target.dataset.action;
            handleQuickAction(action);
        }
    });

    // Send message on button click
    chatbotSend.addEventListener('click', () => {
        sendMessage();
    });

    // Send message on Enter key
    chatbotInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    function handleQuickAction(action) {
        if (action === 'contact') {
            addMessage('Show me contact information', 'user');
            setTimeout(() => {
                showContactInfo();
            }, 500);
            return;
        }

        const nav = navigationMap[action];
        if (nav) {
            addMessage(`Take me to ${nav.label}`, 'user');
            setTimeout(() => {
                addMessage(`Taking you to ${nav.label}...`, 'bot');
                setTimeout(() => {
                    console.log('Navigating to:', nav.url);
                    window.location.href = nav.url;
                }, 1000);
            }, 500);
        } else {
            addMessage(`I don't have that option available.`, 'bot');
        }
    }

    function showContactInfo() {
        const contactHtml = `
            <div style="margin-top: 0.5rem;">
                <p style="margin: 0.5rem 0;"><strong>📍 Address</strong><br>
                P.O Box 52 - 10200<br>
                Murang'a, Kenya</p>
                <p style="margin: 0.5rem 0;"><strong>📞 Contact Number</strong><br>
                <a href="tel:+254757562988" style="color: inherit; text-decoration: underline;">+254 757 562 988</a></p>
                <p style="margin: 0.5rem 0;"><strong>✉️ Email Us</strong><br>
                <a href="mailto:info@muranga.go.ke" style="color: inherit; text-decoration: underline;">info@muranga.go.ke</a></p>
            </div>
        `;

        const messageDiv = document.createElement('div');
        messageDiv.className = 'chatbot-message bot';
        messageDiv.innerHTML = contactHtml;
        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function sendMessage() {
        const message = chatbotInput.value.trim();
        if (!message) return;

        addMessage(message, 'user');
        chatbotInput.value = '';

        // Process message
        setTimeout(() => {
            const response = processMessage(message);
            addMessage(response.text, 'bot');

            if (response.action === 'contact') {
                setTimeout(() => {
                    showContactInfo();
                }, 500);
            } else if (response.action) {
                setTimeout(() => {
                    const nav = navigationMap[response.action];
                    if (nav) {
                        addMessage(`Taking you to ${nav.label}...`, 'bot');
                        setTimeout(() => {
                            window.location.href = nav.url;
                        }, 1000);
                    }
                }, 1500);
            }
        }, 600);
    }

    function addMessage(text, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chatbot-message ${type}`;
        messageDiv.textContent = text;
        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function processMessage(message) {
        const lowerMessage = message.toLowerCase();

        for (const pattern of responsePatterns) {
            for (const keyword of pattern.keywords) {
                if (lowerMessage.includes(keyword)) {
                    return {
                        text: pattern.response,
                        action: pattern.action
                    };
                }
            }
        }

        // Provide appropriate fallback message based on page type
        const fallbackMessage = isAdminPage
            ? 'I\'m not sure I understood that. You can ask me about: home, dashboard, requests, users, or contact information.'
            : 'I\'m not sure I understood that. You can ask me about: home, services, tracking requests, dashboard, sign in, creating an account, or contact information.';

        return {
            text: fallbackMessage,
            action: null
        };
    }

    // Close chatbot when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.chatbot-widget')) {
            chatbotWindow.classList.remove('active');
        }
    });
})();
