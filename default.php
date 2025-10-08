<!DOCTYPE html>
<html>
<head>
    <title>My Website with AI Assistant</title>
    <link rel="stylesheet" href="chatbot-enhanced.css">
</head>
<body>
    <h1>Welcome to the GPT Assistant Boilerplate</h1>
    <p>Your AI assistant is available in the bottom right corner!</p>

    <!-- Enhanced chatbot scripts -->
    <script src="chatbot-enhanced.js"></script>
    <script>
        // Initialize with your existing assistant
        const myAssistant = ChatBot.init({
            apiType: 'assistants',
            mode: 'floating',
            position: 'bottom-right',
            show: false, // Hidden initially, user can open it
            
            // Your assistant configuration
            assistantConfig: {
                assistantId: 'asst_'
            },
            
            // Branding and customization
            title: 'AI Assistant',
            assistant: {
                name: 'My Assistant',
                welcomeMessage: 'Hi! I\'m here to help. You can ask me questions or upload files for analysis.',
                avatar: '/assets/assistant-avatar.png'
            },
            
            // Theme customization
            theme: {
                primaryColor: '#007bff',
                backgroundColor: '#f8f9fa',
                fontFamily: 'Inter, sans-serif'
            },
            
            // File upload support
            enableFileUpload: true,
            
            // Event handlers
            onConnect: function() {
                console.log('Connected to assistant:', 'asst_');
            },
            
            onMessage: function(message) {
                // Track assistant interactions
                if (message.role === 'assistant') {
                    // Your analytics code here
                    gtag('event', 'assistant_message', {
                        'assistant_id': 'asst_',
                        'message_length': message.content.length
                    });
                }
            }
        });

        // Optional: Auto-open assistant based on user behavior
        setTimeout(() => {
            if (!localStorage.getItem('assistant_introduced')) {
                myAssistant.show();
                localStorage.setItem('assistant_introduced', 'true');
            }
        }, 5000);
    </script>
</body>
</html>
