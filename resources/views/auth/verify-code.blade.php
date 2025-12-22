<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>إدخال رمز التحقق</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--teal:#1cc5b7;--teal-dark:#12b0a3;--bg:#f5f7fb;--text:#0f172a;--muted:#64748b;--white:#fff}
    [data-theme="dark"]{--bg:#0b1220;--text:#e5e7eb;--muted:#94a3b8;--white:#0f172a}
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;background:var(--bg);color:var(--text);font-family:'Cairo',system-ui,-apple-system,'Segoe UI',Roboto,Ubuntu,'Helvetica Neue','Noto Sans Arabic',Arial;-webkit-user-select:none;user-select:none}
    .wrap{min-height:100vh;display:grid;place-items:center;padding:24px}
    .card{width:100%;max-width:460px;background:var(--white);border-radius:16px;box-shadow:0 10px 30px rgba(2,8,23,.08);padding:24px}
    .title{margin:0 0 8px;font-weight:800}
    .muted{color:var(--muted)}
    .field{margin:14px 0}
    .input{width:100%;padding:14px 16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;color:var(--text);outline:none;transition:.2s;font-weight:700}
    .input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(28,197,183,.15)}
    .error-text{color:#ef4444;font-size:12px;margin-top:6px}
    .btn{background:var(--teal);border:none;color:#fff;font-weight:800;padding:12px 18px;border-radius:12px;cursor:pointer;width:100%}
    .row{display:flex;align-items:center;justify-content:space-between;margin-top:12px}
    .link{color:var(--teal-dark);text-decoration:none;font-weight:800}
    .theme-toggle{position:fixed;top:12px;left:12px;background:transparent;border:1px solid #e2e8f0;color:var(--text);padding:6px 10px;border-radius:10px;cursor:pointer}
  </style>
</head>
<body>
  <button class="theme-toggle" type="button" onclick="toggleTheme()">الوضع</button>
  <div class="wrap">
    <div class="card">
      <h1 class="title">أدخل رمز التحقق</h1>
      <p class="muted">تم إرسال رمز مكوّن من 6 أرقام إلى بريدك الإلكتروني. الرجاء إدخاله خلال 10 دقائق.</p>
      @if ($errors->any())
        <div class="error-text">{{ $errors->first('code') }}</div>
      @endif
      <form method="post" action="/email/verify-code" id="verify-form">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <input type="hidden" name="code" id="otp-hidden">
        <div class="field" style="display:flex;gap:10px;justify-content:center">
          <input class="input otp" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" autocomplete="one-time-code">
          <input class="input otp" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]">
          <input class="input otp" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]">
          <input class="input otp" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]">
          <input class="input otp" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]">
          <input class="input otp" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]">
        </div>
        <button class="btn" type="submit">تأكيد</button>
      </form>
      <div class="row">
        <form method="post" action="/email/resend-code" id="resend-form">
          <input type="hidden" name="_token" value="{{ csrf_token() }}">
          <button class="link" type="submit" style="background:none;border:none;padding:0">إعادة إرسال الرمز</button>
        </form>
        <a class="link" href="/">العودة</a>
      </div>
    </div>
  </div>
  <script>
    // theme
    (function(){var s=localStorage.getItem('theme');var p=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;document.documentElement.setAttribute('data-theme', s|| (p?'dark':'light'));})();
    function toggleTheme(){var c=document.documentElement.getAttribute('data-theme')||'light';var n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);localStorage.setItem('theme',n);}
    // disable copy/drag/context
    ['contextmenu','copy','cut','dragstart'].forEach(function(ev){document.addEventListener(ev,function(e){e.preventDefault();},{passive:false})});
    document.addEventListener('selectstart', function(e){e.preventDefault();},{passive:false});
    // client validation
    (function(){
      var f=document.getElementById('verify-form'); if(!f) return;
      var boxes=[].slice.call(f.querySelectorAll('.otp'));
      var hidden=document.getElementById('otp-hidden');
      function setHidden(){ hidden.value = boxes.map(function(b){return (b.value||'').replace(/\D/g,'');}).join(''); }
      function focusNext(i){ if(i+1<boxes.length) boxes[i+1].focus(); }
      function focusPrev(i){ if(i>0) boxes[i-1].focus(); }
      boxes.forEach(function(b,idx){
        b.addEventListener('input', function(e){
          this.value=this.value.replace(/\D/g,'').slice(0,1);
          if(this.value){ focusNext(idx); }
          setHidden();
        });
        b.addEventListener('keydown', function(e){
          if((e.key==='Backspace' || e.key==='Delete') && !this.value){ focusPrev(idx); }
        });
        b.addEventListener('paste', function(e){
          var text=(e.clipboardData || window.clipboardData).getData('text');
          if(text){
            e.preventDefault();
            text=text.replace(/\D/g,'').slice(0,6);
            for(var i=0;i<boxes.length;i++){ boxes[i].value = text[i] || ''; }
            setHidden();
            if(text.length<6){ boxes[text.length].focus(); } else { boxes[boxes.length-1].focus(); }
          }
        });
      });
      f.addEventListener('submit',function(e){
        setHidden();
        if(!/^\d{6}$/.test(hidden.value)){
          e.preventDefault();
          var ex=document.querySelector('.error-text');
          if(!ex){ ex=document.createElement('div'); ex.className='error-text'; f.insertBefore(ex, f.firstChild); }
          ex.textContent='الرجاء إدخال رمز صالح من 6 أرقام';
        }
      });
      boxes[0].focus();
    })();
  </script>
</body>
</html>
