// Global variables
let cart = JSON.parse(localStorage.getItem('cart')) || [];

// Page refresh function
function refreshPage() {
    location.reload();
}

// Tab functionality
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabName);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Add active class to clicked button
    const clickedButton = event ? event.target : document.querySelector(`[onclick="showTab('${tabName}')"]`);
    if (clickedButton) {
        clickedButton.classList.add('active');
    }
    
    // Update cart display when cart tab is shown
    if (tabName === 'cart') {
        updateCartDisplay();
    }
}

// Modal functionality
function openModal(modalId) {
    if (modalId === 'checkout-modal') {
        const selectedItems = cart.filter(item => item.selected);
        if (selectedItems.length === 0) {
            alert('Please select items to checkout');
            return;
        }
        
        document.getElementById('checkout-cart-items').value = JSON.stringify(cart);
        
        const subtotal = selectedItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const total = subtotal + 5.00; // Add delivery fee
        document.getElementById('checkout-total-price').value = total.toFixed(2);
    }
    
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Cart functionality
function addToCart(productId, productName, productPrice) {
    const existingItem = cart.find(item => item.id === productId);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: productId,
            name: productName,
            price: parseFloat(productPrice),
            quantity: 1,
            selected: true
        });
    }
    
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartDisplay();
    
    // Show success message
    alert(`${productName} added to cart!`);
}

function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartDisplay();
}

function updateQuantity(productId, change) {
    const item = cart.find(item => item.id === productId);
    if (item) {
        item.quantity += change;
        if (item.quantity <= 0) {
            removeFromCart(productId);
        } else {
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartDisplay();
        }
    }
}

function toggleItemSelection(productId) {
    const item = cart.find(item => item.id === productId);
    if (item) {
        item.selected = !item.selected;
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartDisplay();
    }
}

function updateCartDisplay() {
    const cartItemsContainer = document.getElementById('cart-items');
    const cartCount = document.getElementById('cart-count');
    const cartSubtotal = document.getElementById('cart-subtotal');
    const cartTotal = document.getElementById('cart-total');
    
    if (!cartItemsContainer) return;
    
    // Update cart count
    if (cartCount) {
        cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
    }
    
    if (cart.length === 0) {
        cartItemsContainer.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: #666;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">🛒</div>
                <h3>Your cart is empty</h3>
                <p>Add some delicious items from our menu!</p>
                <button class="btn btn-primary" onclick="showTab('menu')" style="margin-top: 1rem;">
                    Browse Menu
                </button>
            </div>
        `;
        
        if (cartSubtotal) cartSubtotal.textContent = 'RM 0.00';
        if (cartTotal) cartTotal.textContent = 'RM 0.00';
        return;
    }
    
    let cartHTML = '';
    let subtotal = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        if (item.selected) {
            subtotal += itemTotal;
        }
        
        cartHTML += `
            <div class="cart-item">
                <input type="checkbox" class="cart-item-checkbox" ${item.selected ? 'checked' : ''} 
                       onchange="toggleItemSelection('${item.id}')">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">RM ${item.price.toFixed(2)} each</div>
                </div>
                <div class="quantity-controls">
                    <button class="quantity-btn" onclick="updateQuantity('${item.id}', -1)">-</button>
                    <span class="quantity-display">${item.quantity}</span>
                    <button class="quantity-btn" onclick="updateQuantity('${item.id}', 1)">+</button>
                </div>
                <div style="margin-left: 1rem;">
                    <div style="font-weight: 600; color: #28a745;">RM ${itemTotal.toFixed(2)}</div>
                    <button class="btn btn-danger" onclick="removeFromCart('${item.id}')" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; margin-top: 0.5rem;">
                        Remove
                    </button>
                </div>
            </div>
        `;
    });
    
    cartItemsContainer.innerHTML = cartHTML;
    
    // Calculate progressive delivery fee based on selected items quantity
    const selectedItems = cart.filter(item => item.selected);
    const totalQuantity = selectedItems.reduce((sum, item) => sum + item.quantity, 0);
    const deliveryFee = calculateDeliveryFee(totalQuantity);
    const total = subtotal + deliveryFee;
    
    if (cartSubtotal) cartSubtotal.textContent = `RM ${subtotal.toFixed(2)}`;
    
    // Update delivery fee display
    const deliveryFeeElement = document.getElementById('delivery-fee');
    if (deliveryFeeElement) deliveryFeeElement.textContent = `RM ${deliveryFee.toFixed(2)}`;
    
    if (cartTotal) cartTotal.textContent = `RM ${total.toFixed(2)}`;
}

// Calculate progressive delivery fee based on quantity
function calculateDeliveryFee(totalQuantity) {
    if (totalQuantity <= 4) {
        return 5.00;
    } else if (totalQuantity <= 8) {
        return 10.00;
    } else {
        // For 9+ items: RM 15 base + RM 5 for each additional group of 5 items
        const additionalItems = totalQuantity - 9;
        const additionalGroups = Math.floor(additionalItems / 5);
        return 15.00 + (additionalGroups * 5.00);
    }
}

// Profile validation function
function validateProfile() {
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const address = document.getElementById('address').value.trim();
    
    if (!username || !email || !phone || !address) {
        alert('Please fill in all required fields.');
        return false;
    }
    
    // Basic email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address.');
        return false;
    }
    
    return true;
}

// Payment method toggle
function togglePaymentMethod() {
    const paymentMethod = document.getElementById('payment_method').value;
    const qrSection = document.getElementById('qr-payment-section');
    
    if (qrSection) {
        if (paymentMethod === 'qr') {
            qrSection.style.display = 'block';
        } else {
            qrSection.style.display = 'none';
        }
    }
}

// Product editing function for admin
function editProduct(product) {
    document.getElementById('edit_product_id').value = product.product_id;
    document.getElementById('edit_product_name').value = product.product_name;
    document.getElementById('edit_product_info').value = product.product_info;
    document.getElementById('edit_product_price').value = product.product_price;
    document.getElementById('edit_existing_image').value = product.product_image || '';
    
    // Show current image preview
    const imagePreview = document.getElementById('current_image_preview');
    if (product.product_image) {
        imagePreview.innerHTML = `
            <div style="text-align: center;">
                <p style="margin-bottom: 0.5rem; font-weight: 500;">Current Image:</p>
                <img src="${product.product_image}" alt="Current Product Image" style="max-width: 150px; max-height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #e9ecef;">
            </div>
        `;
    } else {
        imagePreview.innerHTML = `
            <div style="text-align: center; padding: 2rem; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">🍔</div>
                <p style="color: #666; margin: 0;">No image uploaded</p>
            </div>
        `;
    }
    
    openModal('edit-product-modal');
}

// Initialize cart display on page load
document.addEventListener('DOMContentLoaded', function() {
    updateCartDisplay();
    
    // Set up tab functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('onclick').match(/showTab\('(.+?)'\)/)[1];
            showTab(tabName);
        });
    });
});