</main>

<footer class="bg-dark text-light py-4 mt-5">
    <div class="container text-center">
        <p class="mb-1"><i class="bi bi-shop me-1"></i><strong>AI Shop</strong> &mdash; Shop smarter with AI</p>
        <small class="text-secondary">&copy; <?= date('Y') ?> AI Shop. All rights reserved.</small>
    </div>
</footer>

<button type="button" id="chatToggle" class="chat-toggle btn btn-primary" aria-label="Open AI assistant">
    <i class="bi bi-robot"></i>
</button>

<div id="chatBox" class="chat-box card shadow d-none">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-robot me-1"></i>AI Assistant</span>
        <button type="button" class="btn-close btn-close-white" id="chatClose" aria-label="Close"></button>
    </div>
    <div class="card-body chat-messages" id="chatMessages"></div>
    <div class="card-footer">
        <div class="input-group">
            <input type="text" id="chatInput" class="form-control" placeholder="Ask about products..." maxlength="300" autocomplete="off">
            <button class="btn btn-primary" type="button" onclick="sendChat()"><i class="bi bi-send"></i> Send</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
