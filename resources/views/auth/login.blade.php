{{-- resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
  >
  <meta
    name="csrf-token"
    content="{{ csrf_token() }}"
  >

  <title>Provincial Agriculture Information System - Login</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap"
    rel="stylesheet"
  >

  <style>
    :root {
      --bg-green: #f6fff4;
      --bg-yellow: #fffbe6;
      --card: #ffffff;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e5e7eb;

      --green: #166534;
      --green-dark: #14532d;
      --green-bright: #22c55e;
      --green-soft: #dcfce7;

      --yellow: #facc15;
      --yellow-dark: #eab308;
      --yellow-soft: #fde68a;

      --danger-bg: #fef2f2;
      --danger-text: #991b1b;
      --danger-border: #fecaca;

      --focus: rgba(34, 197, 94, 0.20);
      --shadow: 0 24px 60px rgba(15, 23, 42, 0.14);
      --shadow-soft: 0 12px 30px rgba(15, 23, 42, 0.08);
      --radius: 24px;
    }

    * {
      box-sizing: border-box;
      font-family:
        "Roboto",
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;
    }

    html {
      min-height: 100%;
    }

    body {
      min-height: 100vh;
      margin: 0;
      color: var(--text);
      background:
        radial-gradient(
          900px 480px at 10% 5%,
          rgba(253, 230, 138, 0.78) 0%,
          transparent 57%
        ),
        radial-gradient(
          900px 480px at 88% 18%,
          rgba(34, 197, 94, 0.20) 0%,
          transparent 58%
        ),
        linear-gradient(
          135deg,
          var(--bg-green),
          var(--bg-yellow)
        );
    }

    button,
    input {
      font: inherit;
    }

    .page {
      position: relative;
      min-height: 100vh;
      display: grid;
      place-items: center;
      overflow: hidden;
      padding: 32px 20px;
    }

    .page::before,
    .page::after {
      content: "";
      position: fixed;
      z-index: 0;
      border-radius: 999px;
      pointer-events: none;
    }

    .page::before {
      width: 320px;
      height: 320px;
      top: -130px;
      right: -110px;
      background: rgba(34, 197, 94, 0.11);
    }

    .page::after {
      width: 280px;
      height: 280px;
      bottom: -130px;
      left: -90px;
      background: rgba(250, 204, 21, 0.15);
    }

    .login-shell {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 470px;
    }

    .login-card {
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(226, 232, 240, 0.95);
      border-radius: var(--radius);
      background: rgba(255, 255, 255, 0.96);
      box-shadow: var(--shadow);
      backdrop-filter: blur(12px);
    }

    .login-card::before {
      content: "";
      display: block;
      height: 8px;
      background: linear-gradient(
        90deg,
        var(--green-bright),
        var(--yellow)
      );
    }

    .login-header {
      padding: 28px 28px 12px;
      text-align: center;
    }

    .logo-wrap {
      width: 92px;
      height: 92px;
      display: grid;
      place-items: center;
      margin: 2px auto 18px;
      border: 1px solid rgba(22, 101, 52, 0.14);
      border-radius: 28px;
      background:
        radial-gradient(
          circle at top left,
          rgba(250, 204, 21, 0.16),
          transparent 48%
        ),
        #ffffff;
      box-shadow:
        0 16px 32px rgba(22, 101, 52, 0.12),
        inset 0 0 0 6px rgba(34, 197, 94, 0.04);
    }

    .logo-img {
      width: 70px;
      height: 70px;
      display: block;
      object-fit: contain;
    }

    .office-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-bottom: 12px;
      padding: 7px 11px;
      border: 1px solid rgba(34, 197, 94, 0.22);
      border-radius: 999px;
      background: rgba(34, 197, 94, 0.09);
      color: var(--green);
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.4px;
      text-transform: uppercase;
    }

    .office-badge-dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: var(--green-bright);
      box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.13);
    }

    .login-title {
      margin: 0;
      color: #0b1220;
      font-size: 24px;
      font-weight: 900;
      line-height: 1.18;
      letter-spacing: -0.4px;
    }

    .login-subtitle {
      max-width: 350px;
      margin: 9px auto 0;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.55;
    }

    .login-form {
      padding: 16px 28px 28px;
    }

    .error-box {
      margin-bottom: 16px;
      padding: 12px 14px;
      border: 1px solid var(--danger-border);
      border-radius: 15px;
      background: var(--danger-bg);
      color: var(--danger-text);
      font-size: 13px;
      line-height: 1.5;
    }

    .error-box strong {
      display: block;
      font-weight: 900;
    }

    .error-box ul {
      margin: 7px 0 0 18px;
      padding: 0;
    }

    .field {
      margin-top: 15px;
    }

    .field:first-of-type {
      margin-top: 0;
    }

    .field-label {
      display: block;
      margin-bottom: 7px;
      color: #334155;
      font-size: 13px;
      font-weight: 900;
    }

    .input-wrap {
      position: relative;
    }

    .input-icon {
      position: absolute;
      top: 50%;
      left: 14px;
      width: 19px;
      height: 19px;
      transform: translateY(-50%);
      color: #94a3b8;
      pointer-events: none;
    }

    .input-icon svg {
      width: 100%;
      height: 100%;
      fill: none;
      stroke: currentColor;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .input {
      width: 100%;
      min-height: 49px;
      padding: 12px 14px 12px 43px;
      border: 1px solid var(--border);
      border-radius: 15px;
      outline: none;
      background: #ffffff;
      color: var(--text);
      font-size: 14px;
      font-weight: 500;
      transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease,
        background 0.15s ease;
    }

    .input::placeholder {
      color: #94a3b8;
      font-weight: 400;
    }

    .input:hover {
      border-color: #cbd5e1;
    }

    .input:focus {
      border-color: rgba(34, 197, 94, 0.72);
      box-shadow: 0 0 0 4px var(--focus);
      background: #ffffff;
    }

    .password-input {
      padding-right: 76px;
    }

    .password-toggle {
      position: absolute;
      top: 50%;
      right: 8px;
      transform: translateY(-50%);
      min-width: 57px;
      padding: 7px 9px;
      border: 1px solid var(--border);
      border-radius: 11px;
      background: #f8fafc;
      color: #334155;
      font-size: 11px;
      font-weight: 900;
      cursor: pointer;
      transition:
        background 0.15s ease,
        border-color 0.15s ease;
    }

    .password-toggle:hover {
      border-color: #cbd5e1;
      background: #f1f5f9;
    }

    .password-toggle:focus {
      outline: none;
      border-color: rgba(34, 197, 94, 0.65);
      box-shadow: 0 0 0 3px var(--focus);
    }

    .form-options {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-top: 15px;
    }

    .remember {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin: 0;
      color: #475569;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      user-select: none;
    }

    .remember input {
      width: 17px;
      height: 17px;
      margin: 0;
      accent-color: var(--green-bright);
      cursor: pointer;
    }

    .login-button {
      width: 100%;
      min-height: 49px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 9px;
      margin-top: 19px;
      padding: 12px 16px;
      border: 0;
      border-radius: 15px;
      background: linear-gradient(
        135deg,
        var(--yellow),
        #fbbf24
      );
      color: #1e293b;
      box-shadow: 0 14px 26px rgba(250, 204, 21, 0.23);
      font-size: 14px;
      font-weight: 900;
      cursor: pointer;
      transition:
        transform 0.12s ease,
        filter 0.15s ease,
        box-shadow 0.15s ease;
    }

    .login-button:hover {
      filter: brightness(0.98);
      transform: translateY(-1px);
      box-shadow: 0 17px 30px rgba(250, 204, 21, 0.28);
    }

    .login-button:active {
      transform: translateY(1px);
    }

    .login-button:focus {
      outline: none;
      box-shadow:
        0 0 0 4px rgba(250, 204, 21, 0.22),
        0 14px 26px rgba(250, 204, 21, 0.23);
    }

    .button-icon {
      width: 18px;
      height: 18px;
      fill: none;
      stroke: currentColor;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .login-footer {
      padding: 15px 22px 19px;
      border-top: 1px solid var(--border);
      background: linear-gradient(
        180deg,
        #ffffff,
        #f8fafc
      );
      color: var(--muted);
      text-align: center;
      font-size: 12px;
      line-height: 1.5;
    }

    .login-footer strong {
      color: #475569;
      font-weight: 900;
    }

    @media (max-width: 520px) {
      .page {
        padding: 18px 13px;
      }

      .login-header {
        padding: 23px 18px 10px;
      }

      .login-form {
        padding: 14px 18px 23px;
      }

      .login-title {
        font-size: 21px;
      }

      .logo-wrap {
        width: 82px;
        height: 82px;
        border-radius: 24px;
      }

      .logo-img {
        width: 62px;
        height: 62px;
      }

      .login-footer {
        padding: 13px 18px 16px;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      *,
      *::before,
      *::after {
        scroll-behavior: auto !important;
        transition: none !important;
        animation: none !important;
      }
    }
  </style>
</head>

<body>
  <main class="page">
    <div class="login-shell">
      <section
        class="login-card"
        aria-labelledby="login-title"
      >
        <header class="login-header">
          <div class="logo-wrap">
            <img
              src="{{ asset('images/da.jpg') }}"
              alt="Department of Agriculture Logo"
              class="logo-img"
            >
          </div>

          <div class="office-badge">
            <span class="office-badge-dot"></span>
            Provincial Agriculture Office
          </div>

          <h1
            class="login-title"
            id="login-title"
          >
            Agriculture Information System
          </h1>

          <p class="login-subtitle">
            Province of Tarlac agricultural records, assistance,
            monitoring, mapping, and municipal operations platform.
          </p>
        </header>

        <form
          method="POST"
          action="{{ route('login.attempt') }}"
          class="login-form"
        >
          @csrf

          @if($errors->any())
            <div
              class="error-box"
              role="alert"
            >
              <strong>Unable to sign in</strong>

              <ul>
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="field">
            <label
              class="field-label"
              for="email"
            >
              Email address
            </label>

            <div class="input-wrap">
              <span
                class="input-icon"
                aria-hidden="true"
              >
                <svg viewBox="0 0 24 24">
                  <path d="M4 4h16v16H4z"></path>
                  <path d="m4 6 8 6 8-6"></path>
                </svg>
              </span>

              <input
                class="input"
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="email"
                placeholder="name@agriculture.gov.ph"
                required
                autofocus
              >
            </div>
          </div>

          <div class="field">
            <label
              class="field-label"
              for="password"
            >
              Password
            </label>

            <div class="input-wrap">
              <span
                class="input-icon"
                aria-hidden="true"
              >
                <svg viewBox="0 0 24 24">
                  <rect
                    x="5"
                    y="10"
                    width="14"
                    height="10"
                    rx="2"
                  ></rect>
                  <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                </svg>
              </span>

              <input
                class="input password-input"
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                placeholder="Enter your password"
                required
              >

              <button
                class="password-toggle"
                type="button"
                id="passwordToggle"
                aria-controls="password"
                aria-label="Show password"
                onclick="togglePassword(this)"
              >
                Show
              </button>
            </div>
          </div>

          <div class="form-options">
            <label class="remember">
              <input
                type="checkbox"
                name="remember"
                value="1"
                @checked(old('remember'))
              >
              <span>Remember me</span>
            </label>
          </div>

          <button
            class="login-button"
            type="submit"
          >
            <svg
              class="button-icon"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path d="M5 12h14"></path>
              <path d="m13 6 6 6-6 6"></path>
            </svg>

            <span>Sign In</span>
          </button>
        </form>

        <footer class="login-footer">
          © {{ date('Y') }}
          <strong>Provincial Agriculture Office</strong><br>
          Province of Tarlac
        </footer>
      </section>
    </div>
  </main>

  <script>
    function togglePassword(button) {
      const passwordInput = document.getElementById('password');

      if (!passwordInput || !button) {
        return;
      }

      const shouldShow = passwordInput.type === 'password';

      passwordInput.type = shouldShow
        ? 'text'
        : 'password';

      button.textContent = shouldShow
        ? 'Hide'
        : 'Show';

      button.setAttribute(
        'aria-label',
        shouldShow
          ? 'Hide password'
          : 'Show password'
      );
    }
  </script>
</body>
</html>