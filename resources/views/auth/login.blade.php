<!-- resources/views/auth/login.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LGU Agriculture System - Login</title>

  <style>
    :root{
      --bg1:#f6fff4;          /* light green */
      --bg2:#fffbe6;          /* light yellow */
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --border:#e5e7eb;

      --green:#166534;        /* deep green */
      --green2:#22c55e;       /* bright green */
      --yellow:#facc15;       /* yellow accent */
      --yellow2:#fde68a;      /* soft yellow */

      --shadow: 0 14px 40px rgba(2,6,23,.10);
      --radius: 18px;

      --dangerBg:#fef2f2;
      --dangerText:#991b1b;
      --dangerBorder:#fecaca;

      --focus: rgba(34,197,94,.20);
    }

    *{ box-sizing: border-box; }
    body{
      margin:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      color: var(--text);
      background:
        radial-gradient(900px 450px at 15% 10%, var(--yellow2) 0%, transparent 55%),
        radial-gradient(900px 450px at 80% 20%, rgba(34,197,94,.18) 0%, transparent 55%),
        linear-gradient(135deg, var(--bg1), var(--bg2));
      min-height: 100vh;
    }

    .wrap{
      min-height: 100vh;
      display:grid;
      place-items:center;
      padding: 26px;
    }

    .card{
      width: 100%;
      max-width: 460px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
      position: relative;
    }

    .card::before{
      content:"";
      display:block;
      height: 10px;
      background: linear-gradient(90deg, var(--green2), var(--yellow));
    }

    .header{
      padding: 22px 22px 10px 22px;
      text-align: center;
    }

    .logos{
      display:flex;
      justify-content:center;
      align-items:center;
      gap: 14px;
      margin: 6px 0 12px;
    }

    .logo-img{
      width: 68px;
      height: 68px;
      object-fit: contain;
      background: #fff;
      border-radius: 18px;
      border: 1px solid rgba(0,0,0,.08);
      padding: 6px;
      box-shadow: 0 10px 20px rgba(34,197,94,.12);
    }

    h1{
      font-size: 20px;
      margin: 0;
      font-weight: 900;
      color: #0b1220;
      letter-spacing: .2px;
    }

    .subtitle{
      margin-top: 6px;
      font-size: 13px;
      color: var(--muted);
    }

    form{
      padding: 16px 22px 22px 22px;
    }

    .field{ margin-top: 12px; }

    label{
      display:block;
      font-size: 13px;
      font-weight: 800;
      color: #334155;
      margin-bottom: 6px;
    }

    .input{
      width:100%;
      padding: 11px 12px;
      border: 1px solid var(--border);
      border-radius: 14px;
      font-size: 14px;
      outline:none;
      transition: box-shadow .15s ease, border-color .15s ease;
      background:#fff;
    }

    .input:focus{
      border-color: rgba(34,197,94,.65);
      box-shadow: 0 0 0 4px var(--focus);
    }

    .hint{
      margin-top: 6px;
      font-size: 12px;
      color: var(--muted);
    }

    .row{
      display:flex;
      justify-content: space-between;
      align-items:center;
      gap: 10px;
      margin-top: 14px;
    }

    .remember{
      display:flex;
      align-items:center;
      gap: 8px;
      font-size: 13px;
      color: #334155;
      user-select:none;
    }
    .remember input{
      width: 16px;
      height: 16px;
      accent-color: var(--green2);
    }

    .btn{
      width:100%;
      margin-top: 14px;
      padding: 11px 12px;
      border:0;
      border-radius: 14px;
      font-weight: 900;
      font-size: 14px;
      cursor:pointer;
      color:#0b1220;
      background: linear-gradient(90deg, var(--yellow), #fbbf24);
      box-shadow: 0 12px 22px rgba(250,204,21,.18);
      transition: transform .06s ease, filter .15s ease;
    }
    .btn:hover{ filter: brightness(.98); }
    .btn:active{ transform: translateY(1px); }

    .alt{
      margin-top: 10px;
      text-align:center;
      font-size: 12px;
      color: var(--muted);
    }

    .errorbox{
      border: 1px solid var(--dangerBorder);
      background: var(--dangerBg);
      color: var(--dangerText);
      border-radius: 14px;
      padding: 10px 12px;
      font-size: 13px;
      margin-top: 12px;
    }
    .errorbox ul{ margin: 6px 0 0 18px; padding: 0; }

    .footer{
      text-align:center;
      font-size: 12px;
      color: var(--muted);
      padding: 12px 22px 18px;
      border-top: 1px solid var(--border);
      background: linear-gradient(180deg, #ffffff, #f8fafc);
    }

    .pw-wrap{ position:relative; }
    .pw-toggle{
      position:absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      border: 1px solid var(--border);
      background: #fff;
      border-radius: 12px;
      padding: 6px 10px;
      font-size: 12px;
      cursor:pointer;
      color:#334155;
    }
    .pw-toggle:hover{ background:#f8fafc; }

    @media (max-width: 420px){
      .header{ padding: 18px 16px 8px 16px; }
      form{ padding: 12px 16px 18px 16px; }
      .footer{ padding: 10px 16px 14px; }
      .logo-img{ width: 58px; height: 58px; border-radius: 16px; }
    }
  </style>
</head>

<body>
  <div class="wrap">
    <div class="card">
      <div class="header">
        <div class="logos">

          <img src="{{ asset('images/ramos.jpg') }}" alt="Municipality of Ramos Logo" class="logo-img">
          <img src="{{ asset('images/da.jpg') }}" alt="Department of Agriculture Logo" class="logo-img">
        </div>

        <h1>LGU Ramos - Department of Agriculture System</h1>
        <div class="subtitle">Municipality of Ramos, Tarlac</div>
      </div>

      <form method="POST" action="{{ route('login.attempt') }}">
        @csrf

        @if($errors->any())
          <div class="errorbox">
            <strong>Please fix the following:</strong>
            <ul>
              @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div class="field">
          <label for="email">Email</label>
          <input class="input" id="email" name="email" type="email"
                 value="{{ old('email') }}" required autofocus
                 placeholder="you@lgu.gov.ph">

        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="pw-wrap">
            <input class="input" id="password" name="password" type="password" required
                   placeholder="Enter your password">
            <button class="pw-toggle" type="button" onclick="togglePw()">Show</button>
          </div>
        </div>

        <div class="row">
          <label class="remember">
            <input type="checkbox" name="remember" value="1">
            Remember me
          </label>
        </div>

        <button class="btn" type="submit">Sign In</button>
       
      </form>

      <div class="footer">
        © {{ date('Y') }} LGU Agriculture Office
      </div>
    </div>
  </div>

  <script>
    function togglePw(){
      const pw = document.getElementById('password');
      const btn = event.target;
      const show = pw.type === 'password';
      pw.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
    }
  </script>
</body>
</html>
