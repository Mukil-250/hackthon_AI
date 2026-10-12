'use strict';

const formatINR = (n) => '₹' + Number(n).toLocaleString('en-IN');

/* ---------- Cart ---------- */
async function updateCartCount() {
    try {
        const res = await fetch('api/cart_action.php?action=count', { credentials: 'same-origin' });
        const data = await res.json();
        const el = document.getElementById('cartCount');
        if (el && typeof data.count === 'number') el.textContent = data.count;
    } catch (err) {
        console.error('Cart count error:', err);
    }
}

async function addToCart(pid, qty = 1) {
    try {
        const body = new URLSearchParams({ action: 'add', pid: String(pid), qty: String(qty) });
        const res = await fetch('api/cart_action.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        });
        const data = await res.json();
        if (data.success) {
            updateCartCount();
            toast('Added to cart');
        } else {
            toast(data.message || 'Could not add to cart', 'danger');
        }
    } catch (err) {
        console.error('Add to cart error:', err);
        toast('Something went wrong. Please try again.', 'danger');
    }
}

/* ---------- UI helpers ---------- */
function toast(msg, type = 'success') {
    const div = document.createElement('div');
    div.className = `alert alert-${type} toast-float py-2 px-3 mb-0`;
    div.setAttribute('role', 'alert');
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 2000);
}

function debounce(fn, wait) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), wait);
    };
}

/* ---------- Live search ---------- */
function removeSuggestBox() {
    const box = document.getElementById('suggestBox');
    if (box) box.remove();
}

function renderSuggestions(input, items) {
    removeSuggestBox();
    const wrap = input.closest('.search-wrap') || input.parentElement;
    const box = document.createElement('div');
    box.id = 'suggestBox';

    if (!items.length) {
        const empty = document.createElement('div');
        empty.className = 'suggest-empty';
        empty.textContent = 'No matching products';
        box.appendChild(empty);
    } else {
        items.forEach((item) => {
            const a = document.createElement('a');
            a.href = 'product.php?id=' + encodeURIComponent(item.id);
            const name = document.createElement('span');
            name.textContent = item.name;
            const price = document.createElement('span');
            price.className = 'suggest-price';
            price.textContent = formatINR(item.price);
            a.append(name, price);
            box.appendChild(a);
        });
    }
    wrap.appendChild(box);
}

function initLiveSearch() {
    const input = document.getElementById('searchInput');
    if (!input) return;

    const doSearch = debounce(async () => {
        const q = input.value.trim();
        if (q.length < 2) { removeSuggestBox(); return; }
        try {
            const res = await fetch('api/ai_search.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' });
            const data = await res.json();
            if (input.value.trim() !== q) return; // stale response
            renderSuggestions(input, Array.isArray(data.items) ? data.items : []);
        } catch (err) {
            console.error('Search error:', err);
        }
    }, 300);

    input.addEventListener('input', () => {
        if (input.value.trim().length < 2) removeSuggestBox();
        doSearch();
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-wrap')) removeSuggestBox();
    });
    input.addEventListener('keydown', (e) => { if (e.key === 'Escape') removeSuggestBox(); });
}

/* ---------- Chatbot ---------- */
let chatGreeted = false;

function addChat(who, text) {
    const msgs = document.getElementById('chatMessages');
    if (!msgs) return;
    const div = document.createElement('div');
    div.className = 'chat-msg ' + (who === 'user' ? 'chat-user' : 'chat-bot');
    div.textContent = text;
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
}

async function sendChat() {
    const input = document.getElementById('chatInput');
    if (!input) return;
    const message = input.value.trim();
    if (!message) return;

    addChat('user', message);
    input.value = '';

    try {
        const res = await fetch('api/ai_chat.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ message })
        });
        const data = await res.json();
        addChat('bot', data.reply || 'Sorry, I could not understand that.');
    } catch (err) {
        console.error('Chat error:', err);
        addChat('bot', 'Sorry, something went wrong. Please try again.');
    }
}

function initChat() {
    const toggle = document.getElementById('chatToggle');
    const box = document.getElementById('chatBox');
    const close = document.getElementById('chatClose');
    const input = document.getElementById('chatInput');
    if (!toggle || !box) return;

    toggle.addEventListener('click', () => {
        box.classList.toggle('d-none');
        if (!box.classList.contains('d-none')) {
            if (!chatGreeted) {
                chatGreeted = true;
                addChat('bot', 'Hi! I\'m your AI shopping assistant. Ask me about products, deals or recommendations.');
            }
            if (input) input.focus();
        }
    });
    if (close) close.addEventListener('click', () => box.classList.add('d-none'));
    if (input) input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); sendChat(); }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    updateCartCount();
    initLiveSearch();
    initChat();
});
