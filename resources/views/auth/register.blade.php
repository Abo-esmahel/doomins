<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل جديد</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--teal:#1cc5b7;--teal-dark:#12b0a3;--bg:#f5f7fb;--text:#0f172a;--muted:#64748b;--white:#fff;--shadow:0 10px 30px rgba(2,8,23,.08)}
        [data-theme="dark"]{--bg:#0b1220;--text:#e5e7eb;--muted:#94a3b8;--white:#0f172a;--shadow:0 10px 30px rgba(0,0,0,.5)}
        *{box-sizing:border-box}
        html,body{height:100%}
        body{margin:0;background:var(--white);font-family:'Cairo',system-ui,-apple-system,'Segoe UI',Roboto,Ubuntu,'Helvetica Neue','Noto Sans Arabic',Arial,'Apple Color Emoji','Segoe UI Emoji',sans-serif;color:var(--text);-webkit-user-select:none;user-select:none;overflow:hidden;font-weight:700}
        .page{width:100vw;height:100vh;margin:0;overflow:hidden}
        .card{display:grid;grid-template-columns:1.1fr 1fr;height:100vh}
        .hero{background:var(--teal);position:relative;display:flex;align-items:center;justify-content:center;padding:32px}
        .hero::before{content:"";position:absolute;inset:0;background-image:url('/image/image copy 3.png'),url('/image/image copy 3.png');background-repeat:no-repeat;background-size:220px,160px;background-position:78% 24%,18% 66%;opacity:.18;pointer-events:none}
        .hero-inner{position:relative;width:100%;height:100%;display:flex;align-items:center;justify-content:center}
        .logo{position:absolute;top:24px;right:24px;width:120px;height:auto}
        .hero-art{max-width:85%;height:auto;animation:floaty 4s ease-in-out infinite}
        .form-pane{position:relative;background:var(--white);padding:48px 40px;border-top-left-radius:24px;border-bottom-left-radius:24px;box-shadow:-12px 0 30px rgba(2,8,23,.06);overflow-y:auto;-webkit-overflow-scrolling:touch}
        .panel{max-width:520px;margin:0 auto}
        .title{font-weight:800;font-size:24px;margin:0 0 8px}
        .subtitle{margin:0 0 20px}
        .subtitle img{max-width:340px;height:auto}
        .field{margin:10px 0}
        .input{width:100%;padding:14px 16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;color:var(--text);outline:none;transition:.2s}
        .input-wrap{position:relative}
        .toggle-pass{position:absolute;left:12px;top:50%;transform:translateY(-50%);background:transparent;border:none;cursor:pointer;color:#64748b;padding:4px;border-radius:8px}
        .toggle-pass:focus{outline:none}
        .toggle-pass svg{width:20px;height:20px;transition:transform .18s ease,opacity .18s ease}
        .toggle-pass.blink .eye-on,.toggle-pass.blink .eye-off{transform:scale(1.15);}
        .input.error{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.15)}
        .error-text{color:#ef4444;font-size:12px;margin-top:6px}
        .strength{margin-top:8px}
        .bar{height:8px;border-radius:999px;background:#e2e8f0;overflow:hidden}
        .bar > span{display:block;height:100%;width:0;background:#ef4444;border-radius:999px;transition:width .25s ease, background .25s ease}
        .meter-row{display:flex;align-items:center;justify-content:space-between;margin-top:6px}
        .meter-label{font-size:12px;color:#64748b}
        .input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(28,197,183,.15)}
        .actions{margin-top:14px;display:flex;align-items:center;gap:8px;justify-content:center}
        .btn{background:var(--teal);border:none;color:#fff;font-weight:700;padding:12px 18px;border-radius:12px;cursor:pointer;transition:.2s}
        .btn:hover{background:var(--teal-dark)}
        .alt{margin-top:18px;color:var(--muted);font-size:14px;text-align:center}
        .providers{display:flex;gap:16px;margin-top:12px;align-items:center;justify-content:center}
        .provider{display:flex;flex-direction:column;align-items:center;gap:6px}
        .provider .circle{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;border:1px solid #e2e8f0;background:#fff;box-shadow:0 4px 12px rgba(2,8,23,.06)}
        .provider span{font-size:12px;color:var(--muted)}
        .muted{color:var(--muted);font-size:13px}
        .split{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .alt-title{position:relative;margin:16px 0 8px;text-align:center;font-weight:800}
        .alt-title::before{content:"";position:absolute;left:0;right:0;top:50%;height:2px;background:#000;transform:translateY(-50%)}
        .alt-title span{position:relative;z-index:1;background:#fff;padding:0 12px}
        .link-pill{display:inline-block;margin-top:10px;padding:8px 14px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:var(--teal-dark);text-decoration:none;font-weight:700;}
        .copyright{position:fixed;left:0;right:0;bottom:10px;text-align:center;color:#94a3b8;font-size:12px}
        @keyframes floaty{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
        .theme-toggle{position:absolute;top:14px;left:14px;background:transparent;border:1px solid #e2e8f0;color:var(--text);padding:6px 10px;border-radius:10px;cursor:pointer}
        [data-theme="dark"] .input{background:#0b162a;color:var(--text);border-color:#1e293b}
        [data-theme="dark"] .panel .alt{color:var(--muted)}
        @media (max-width:980px){
          .page{height:100vh}
          .card{grid-template-columns:1fr;grid-template-rows:240px 1fr;height:100vh}
          .hero{order:-1;min-height:240px;padding:24px}
          .hero::before{background-size:160px,120px;background-position:75% 22%,12% 68%}
          .form-pane{border-radius:0;box-shadow:none;padding:24px;height:calc(100vh - 240px);overflow-y:auto}
          .logo{right:16px;top:16px;width:96px}
          .subtitle img{max-width:260px}
        }
        img{-webkit-user-drag:none;user-drag:none}
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <div class="hero">
            <div class="hero-inner">
                <img class="logo" src="/image/Layer 1 copy (1) (1).png" alt="logo" draggable="false">
                <img class="hero-art" src="/image/7954518.png" alt="illustration" draggable="false">
            </div>
        </div>
        <div class="form-pane">
            <button class="theme-toggle" type="button" onclick="toggleTheme()">الوضع</button>
            <div class="panel">
                <h1 class="title">تسجيل جديد</h1>
                <div class="subtitle"><img src="/image/image copy.png" alt="مرحباً بك"></div>
                <form method="post" action="/register">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <div class="field"><input class="input" type="text" name="name" placeholder="الاسم الكامل"></div>
                    <div class="field"><input class="input" type="email" name="email" placeholder="البريد الإلكتروني"></div>
                    <div class="split">
                        <div class="field input-wrap">
                            <input id="reg-password" class="input" type="password" name="password" placeholder="كلمة المرور" minlength="8" autocomplete="new-password">
                            <button type="button" class="toggle-pass" aria-label="عرض/إخفاء" onclick="togglePassword('reg-password', this)">
                                <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.29 20.29 0 0 1 5.06-6.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a20.29 20.29 0 0 1-4.2 5.4"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <div class="field input-wrap">
                            <input id="reg-password-confirm" class="input" type="password" name="password_confirmation" placeholder="تأكيد كلمة المرور" minlength="8" autocomplete="new-password">
                            <button type="button" class="toggle-pass" aria-label="عرض/إخفاء" onclick="togglePassword('reg-password-confirm', this)">
                                <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.29 20.29 0 0 1 5.06-6.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a20.29 20.29 0 0 1-4.2 5.4"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="strength" id="pwd-strength">
                        <div class="bar"><span id="pwd-bar"></span></div>
                        <div class="meter-row">
                            <div class="meter-label" id="pwd-label">قوة كلمة المرور: ضعيفة</div>
                            <div class="meter-label" id="pwd-hints">استخدم حروف كبيرة وصغيرة، أرقام ورموز</div>
                        </div>
                    </div>
                    <label class="muted" style="display:flex;align-items:center;gap:8px;margin-top:6px"><input type="checkbox"> الموافقة على الشروط والبنود</label>
                    <div class="actions">
                        <button class="btn" type="submit">إنشاء حساب</button>
                    </div>
                    <div class="alt">هل تملك حساب ؟ <a class="link-pill" href="/login">سجل دخول</a></div>
                    <div class="alt-title"><span>تسجيل آخر</span></div>
                    <div class="providers">
                        <div class="provider">
                            <div class="circle"> 
                                <svg width="20" height="20" viewBox="0 0 256 262" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M255.878 133.451c0-10.734-.871-18.567-2.746-26.69H130.55v48.448h71.947c-1.452 12.04-9.285 30.172-26.69 42.356l-.243 1.62 38.742 30.023 2.683.268c24.659-22.774 38.89-56.282 38.89-96.475"/><path fill="#34A853" d="M130.55 261.1c35.248 0 64.839-11.614 86.453-31.622l-41.196-31.946c-11.03 7.688-25.82 13.055-45.257 13.055-34.523 0-63.824-22.773-74.269-54.25l-1.531.13-40.3 31.187-.527 1.465C34.28 231.798 79.49 261.1 130.55 261.1"/><path fill="#FBBC05" d="M56.281 156.337a77.806 77.806 0 0 1-4.121-25.105c0-8.748 1.5-17.146 3.965-25.105l-.069-1.682L14.985 72.776l-1.335.63C5.173 89.172.2 106.58.2 125.232c0 18.651 4.973 36.06 13.45 51.827l41.63-20.722"/><path fill="#EB4335" d="M130.55 49.408c24.516 0 41.042 10.588 50.467 19.438l36.844-35.974C195.318 11.78 165.798.2 130.55.2 79.49.2 34.28 29.503 15.85 73.405l41.275 31.722c10.6-31.477 39.901-55.719 73.425-55.719"/></svg>
                            </div>
                            <span>جوجل</span>
                        </div>
                        <div class="provider">
                            <div class="circle">
                                <svg width="20" height="20" viewBox="0 0 256 262" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M255.878 133.451c0-10.734-.871-18.567-2.746-26.69H130.55v48.448h71.947c-1.452 12.04-9.285 30.172-26.69 42.356l-.243 1.62 38.742 30.023 2.683.268c24.659-22.774 38.89-56.282 38.89-96.475"/><path fill="#34A853" d="M130.55 261.1c35.248 0 64.839-11.614 86.453-31.622l-41.196-31.946c-11.03 7.688-25.82 13.055-45.257 13.055-34.523 0-63.824-22.773-74.269-54.25l-1.531.13-40.3 31.187-.527 1.465C34.28 231.798 79.49 261.1 130.55 261.1"/><path fill="#FBBC05" d="M56.281 156.337a77.806 77.806 0 0 1-4.121-25.105c0-8.748 1.5-17.146 3.965-25.105l-.069-1.682L14.985 72.776l-1.335.63C5.173 89.172.2 106.58.2 125.232c0 18.651 4.973 36.06 13.45 51.827l41.63-20.722"/><path fill="#EB4335" d="M130.55 49.408c24.516 0 41.042 10.588 50.467 19.438l36.844-35.974C195.318 11.78 165.798.2 130.55.2 79.49.2 34.28 29.503 15.85 73.405l41.275 31.722c10.6-31.477 39.901-55.719 73.425-55.719"/></svg>
                            </div>
                            <span>جوجل</span>
                        </div>
                        <div class="provider">
                            <div class="circle">
                                <svg width="20" height="20" viewBox="0 0 256 262" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M255.878 133.451c0-10.734-.871-18.567-2.746-26.69H130.55v48.448h71.947c-1.452 12.04-9.285 30.172-26.69 42.356ل-.243 1.62 38.742 30.023 2.683.268c24.659-22.774 38.89-56.282 38.89-96.475"/><path fill="#34A853" d="M130.55 261.1c35.248 0 64.839-11.614 86.453-31.622ل-41.196-31.946c-11.03 7.688-25.82 13.055-45.257 13.055-34.523 0-63.824-22.773-74.269-54.25ل-1.531.13-40.3 31.187-.527 1.465C34.28 231.798 79.49 261.1 130.55 261.1"/><path fill="#FBBC05" d="M56.281 156.337a77.806 77.806 0 0 1-4.121-25.105c0-8.748 1.5-17.146 3.965-25.105ل-.069-1.682L14.985 72.776ل-1.335.63C5.173 89.172.2 106.58.2 125.232c0 18.651 4.973 36.06 13.45 51.827ل41.63-20.722"/><path fill="#EB4335" d="M130.55 49.408c24.516 0 41.042 10.588 50.467 19.438ل36.844-35.974C195.318 11.78 165.798.2 130.55.2 79.49.2 34.28 29.503 15.85 73.405ل41.275 31.722c10.6-31.477 39.901-55.719 73.425-55.719"/></svg>
                            </div>
                            <span>جوجل</span>
                        </div>
                        <div class="provider">
                            <div class="circle">
                                <svg width="20" height="20" viewBox="0 0 256 262" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M255.878 133.451c0-10.734-.871-18.567-2.746-26.69H130.55v48.448h71.947c-1.452 12.04-9.285 30.172-26.69 42.356ل-.243 1.62 38.742 30.023 2.683.268c24.659-22.774 38.89-56.282 38.89-96.475"/><path fill="#34A853" d="M130.55 261.1c35.248 0 64.839-11.614 86.453-31.622ل-41.196-31.946c-11.03 7.688-25.82 13.055-45.257 13.055-34.523 0-63.824-22.773-74.269-54.25ل-1.531.13-40.3 31.187-.527 1.465C34.28 231.798 79.49 261.1 130.55 261.1"/><path fill="#FBBC05" d="M56.281 156.337a77.806 77.806 0 0 1-4.121-25.105c0-8.748 1.5-17.146 3.965-25.105ل-.069-1.682L14.985 72.776ل-1.335.63C5.173 89.172.2 106.58.2 125.232c0 18.651 4.973 36.06 13.45 51.827ل41.63-20.722"/><path fill="#EB4335" d="M130.55 49.408c24.516 0 41.042 10.588 50.467 19.438ل36.844-35.974C195.318 11.78 165.798.2 130.55.2 79.49.2 34.28 29.503 15.85 73.405ل41.275 31.722c10.6-31.477 39.901-55.719 73.425-55.719"/></svg>
                            </div>
                            <span>جوجل</span>
                        </div>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>
</div>
<div class="copyright">© 2025 PAC Team</div>
</body>
</html>
<script>
  // Theme handling: system preference with local override
  (function(){
    var saved = localStorage.getItem('theme');
    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    var theme = saved || (prefersDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
  })();
  function toggleTheme(){
    var cur = document.documentElement.getAttribute('data-theme') || 'light';
    var next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
  }
  function togglePassword(id, btn){
    var i = document.getElementById(id); if(!i) return;
    var visible = i.type === 'text';
    i.type = visible ? 'password' : 'text';
    var on = btn.querySelector('.eye-on');
    var off = btn.querySelector('.eye-off');
    if(on && off){ on.style.display = visible ? '' : 'none'; off.style.display = visible ? 'none' : ''; }
    btn.classList.add('blink'); setTimeout(function(){btn.classList.remove('blink')},180);
  }

  // Disable context menu/copy/drag on register page
  ['contextmenu','copy','cut','dragstart'].forEach(function(ev){document.addEventListener(ev,function(e){e.preventDefault();}, {passive:false});});
  document.addEventListener('selectstart', function(e){e.preventDefault();}, {passive:false});

  (function(){
    var input = document.getElementById('reg-password');
    if(!input) return;
    var bar = document.getElementById('pwd-bar');
    var label = document.getElementById('pwd-label');
    var hints = document.getElementById('pwd-hints');

    function penalties(val){
      var p = 0;
      if(/(.)\1{2,}/.test(val)) p++; // 3+ repeats
      if(/(?:012|123|234|345|456|567|678|789|890)/.test(val)) p++;
      if(/(?:abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i.test(val)) p++;
      return p;
    }

    function score(val){
      if(!val) return 0;
      var len = val.length;
      var lenScore = len >= 15 ? 3 : len >= 11 ? 2 : len >= 8 ? 1 : 0;
      var variety = 0;
      if(/[a-z]/.test(val)) variety++;
      if(/[A-Z]/.test(val)) variety++;
      if(/[0-9]/.test(val)) variety++;
      if(/[^A-Za-z0-9]/.test(val)) variety++;
      var s = lenScore + variety;
      s -= penalties(val);
      s = Math.max(0, Math.min(5, s));
      return s; // 0..5
    }

    function update(){
      var v = input.value || '';
      var sc = score(v);
      var pct = [0,20,40,60,80,100][sc];
      var colors = ['#ef4444','#f97316','#f59e0b','#10b981','#0ea5e9'];
      bar.style.width = pct + '%';
      bar.style.background = colors[Math.max(0, Math.min(colors.length-1, sc-1))] || '#ef4444';
      var texts = ['ضعيفة جداً','ضعيفة','متوسطة','قوية','قوية جداً'];
      label.textContent = 'قوة كلمة المرور: ' + texts[Math.max(0, Math.min(texts.length-1, sc-1))];
      hints.style.opacity = sc >= 4 ? 0.6 : 1;
    }
    input.addEventListener('input', update);
    update();
  })();

  // Client validation for register
  (function(){
    var form = document.querySelector('form[action="/register"]'); if(!form) return;
    function setErr(input, msg){
      input.classList.add('error');
      var wrap = input.parentElement; if(!wrap) return;
      var ex = wrap.querySelector('.error-text'); if(!ex){ ex = document.createElement('div'); ex.className='error-text'; wrap.appendChild(ex); }
      ex.textContent = msg || '';
    }
    function clearErr(input){
      input.classList.remove('error');
      var wrap = input.parentElement; if(!wrap) return;
      var ex = wrap.querySelector('.error-text'); if(ex) ex.textContent='';
    }
    function validate(){
      var ok = true;
      var name = form.querySelector('input[name="name"]');
      var email = form.querySelector('input[name="email"]');
      var pass = form.querySelector('input[name="password"]');
      var pass2 = form.querySelector('input[name="password_confirmation"]');
      if(!name.value){ setErr(name,'هذا الحقل مطلوب'); ok=false; } else { clearErr(name); }
      if(!email.value){ setErr(email,'هذا الحقل مطلوب'); ok=false; } else { clearErr(email); }
      if(!pass.value){ setErr(pass,'هذا الحقل مطلوب'); ok=false; } else { clearErr(pass); }
      if(!pass2.value){ setErr(pass2,'هذا الحقل مطلوب'); ok=false; } else { clearErr(pass2); }
      if(pass.value && pass2.value && pass.value !== pass2.value){ setErr(pass2,'كلمتا المرور غير متطابقتين'); ok=false; }
      return ok;
    }
    ['input','blur'].forEach(function(ev){
      form.addEventListener(ev,function(e){
        var t = e.target; if(!(t && t.classList && t.classList.contains('input'))) return;
        if(!t.value){ setErr(t,'هذا الحقل مطلوب'); } else { clearErr(t); }
        if(t.name === 'password' || t.name === 'password_confirmation'){
          var p = form.querySelector('input[name="password"]');
          var pc = form.querySelector('input[name="password_confirmation"]');
          if(p.value && pc.value && p.value !== pc.value){ setErr(pc,'كلمتا المرور غير متطابقتين'); } else { clearErr(pc); }
        }
      },true);
    });
    form.addEventListener('submit', function(e){ if(!validate()){ e.preventDefault(); } });
  })();
</script>
