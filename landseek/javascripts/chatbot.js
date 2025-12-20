const chatBtn = document.getElementById('chatBotBtn'); // button to open
const chatWindow = document.getElementById('chatWindow'); // chat window
const closeChat = document.getElementById('closeChat'); // close button
const chatBody = document.getElementById('chatBody'); // chat messages area

// Automatic conversation flow
const conversation = [
    "👋 Hi! Welcome to LandSeek. <br> <br>",
    "I can guide you through Registering, Buying, or Selling land. <br> <br>",
    "To register: Click 'Register' at the top, fill in details, and verify your email. <br> <br>",
    "To buy: Browse properties, click 'Inquire' and chat directly with sellers. <br> <br>",
    "To sell: Upload your property details, and interested buyers will reach out. <br> <br>",
    "You can also use the navigation bar for Home, About, Services, and Contact pages. <br> <br>",
    "That's all! Thanks for using LandSeek. <br> <br>"
];

// Current step in conversation
let step = 0;

// Show message in chat
function showMessage(text) {
    let msg = document.createElement('p');
    msg.innerHTML = `<b>Bot:</b> ${text}`;
    chatBody.appendChild(msg);
    chatBody.scrollTop = chatBody.scrollHeight;
}

// Open chatbot and start conversation
chatBtn.addEventListener('click', () => {
    chatWindow.style.display = 'block';
    chatBody.innerHTML = ""; // clear old chat
    step = 0;
    runConversation();
});

// Close chatbot
closeChat.addEventListener('click', () => {
    chatWindow.style.display = 'none';
});

// Run automatic flow
function runConversation() {
    if (step < conversation.length) {
    showMessage(conversation[step]);
    step++;
    setTimeout(runConversation, 2500); // wait 2.5s then continue
    }
}