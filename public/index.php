<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hassan Trade Panel</title>
  <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body class="<?php echo $user ? 'logged-in' : 'logged-out'; ?>">
  <?php if (!$user): ?>
  <main class="auth-shell">
    <section class="auth-box" id="authCard">
      <div class="auth-brand">
        <div>
          <h1>Hassan Trade</h1>
          <p>ERP Access Panel</p>
        </div>
      </div>

      <div class="auth-panel">
        <h2 id="authTitle">Sign In</h2>
        <p id="authSub">Use your username and password to continue.</p>
        <div id="authMessage" class="auth-message" aria-live="polite"></div>

        <div class="tab-pane active" id="pane-signin">
          <form id="loginForm">
            <input type="text" name="username" placeholder="User Name" required>
            <div class="password-wrap">
              <input type="password" name="password" placeholder="Password" required>
              <button type="button" class="password-toggle" data-target="password" aria-label="Show password" title="Show password">&#128065;</button>
            </div>
            <button type="submit" class="primary-btn">Login</button>
          </form>
        </div>

        <div class="tab-pane" id="pane-signup">
          <form id="signupForm">
            <input type="text" name="username" placeholder="Choose User Name" required>
            <input type="text" name="phone" placeholder="Phone" required>
            <input type="email" name="email" placeholder="Email (optional)">
            <div class="password-wrap">
              <input type="password" name="password" placeholder="Password" required>
              <button type="button" class="password-toggle" data-target="password" aria-label="Show password" title="Show password">&#128065;</button>
            </div>
            <button type="submit" class="primary-btn">Create Account</button>
          </form>
        </div>

        <div class="tab-pane" id="pane-forgot">
          <form id="forgotForm">
            <input type="text" name="username" placeholder="User Name" required>
            <div class="password-wrap">
              <input type="password" name="new_password" placeholder="New Password" required>
              <button type="button" class="password-toggle" data-target="new_password" aria-label="Show password" title="Show password">&#128065;</button>
            </div>
            <button type="submit" class="primary-btn">Reset Password</button>
          </form>
        </div>

        <div class="auth-links">
          <button type="button" class="switch-link active" data-tab="signin">Sign In</button>
          <button type="button" class="switch-link" data-tab="signup">Sign Up</button>
          <button type="button" class="switch-link" data-tab="forgot">Forgot Password?</button>
        </div>
      </div>
    </section>
  </main>
  <?php else: ?>
  <div class="bg-grid"></div>
  <main class="shell">
    <header class="hero">
      <h1>Hassan Trade</h1>
      <p>Orders, Inventory, Vouchers, Recovery, Customer Tracking</p>
      <div class="session">
        <span>Logged in: <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['role']); ?>)</span>
      </div>
    </header>

    <section id="appPanel">
      <section class="card two-col">
        <div>
          <h2>Create Order (Admin/Staff)</h2>
          <form id="orderForm">
            <input type="text" id="customerSearch" placeholder="Search Customer by Name" autocomplete="off" required>
            <input type="hidden" name="customer_party_id" id="customerPartyId" required>
            <div id="customerSearchResults" class="search-results"></div>
            <input type="datetime-local" name="delivery_date" required>
            <input type="number" step="0.01" name="estimate_amount" placeholder="Estimate Amount" required>
            <input type="number" step="0.01" name="advance_amount" placeholder="Advance Amount" required>
            <button type="submit">Create Order</button>
          </form>
        </div>
        <div>
          <h2>Quick Notes</h2>
          <p>Order form opens only after successful sign in.</p>
          <p>Customer search works by customer name, not by customer id.</p>
        </div>
      </section>

      <section class="card two-col">
        <div>
          <h2>Complete Order + WhatsApp</h2>
          <form id="completeForm">
            <input type="number" name="order_id" placeholder="Order ID" required>
            <input type="number" step="0.01" name="final_bill_amount" placeholder="Final Bill Amount" required>
            <button type="submit">Mark Complete</button>
          </form>
        </div>
        <div>
          <h2>Inventory Movement</h2>
          <form id="stockForm">
            <input type="number" name="item_id" placeholder="Item ID" required>
            <select name="movement_type">
              <option value="purchase">Purchase</option>
              <option value="sale">Sale</option>
            </select>
            <input type="number" step="0.001" name="quantity" placeholder="Qty" required>
            <input type="text" name="unit_name" placeholder="Unit (tons/kg/feet/pieces/base)" required>
            <input type="number" step="0.0001" name="unit_rate" placeholder="Rate" required>
            <button type="submit">Post Stock</button>
          </form>
        </div>
      </section>
    </section>
  </main>
  <?php endif; ?>

  <script>
    window.CURRENT_USER = <?php echo json_encode($user ?: null, JSON_UNESCAPED_UNICODE); ?>;
  </script>
  <script src="/public/assets/js/app.js"></script>
</body>
</html>
