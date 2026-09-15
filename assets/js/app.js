/* STORYFOUNDRY front-end helpers. No dependencies. */
(function(){
  var CSRF = (document.querySelector('meta[name=csrf]')||{}).content || '';
  var BASE = (document.querySelector('meta[name=base]')||{}).content || '';

  function toast(msg, kind){
    var host = document.getElementById('flashes') || (function(){
      var d=document.createElement('div'); d.id='flashes'; d.className='flashes'; document.body.appendChild(d); return d;
    })();
    var el=document.createElement('div'); el.className='flash '+(kind||''); el.textContent=msg;
    host.appendChild(el);
    setTimeout(function(){ el.style.transition='.3s'; el.style.opacity=0; setTimeout(function(){el.remove();},320); }, kind==='err'?5200:3400);
  }
  window.sfToast = toast;

  /* sfApi('plugin.action', {..}) -> Promise */
  window.sfApi = function(action, data){
    return fetch(BASE + 'index.php?r=api&a=' + encodeURIComponent(action), {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-CSRF':CSRF,'X-Requested-With':'XMLHttpRequest'},
      body: JSON.stringify(data||{})
    }).then(function(r){ return r.json(); }).then(function(j){
      if(j && j.error){ toast(j.error,'err'); }
      return j;
    }).catch(function(e){ toast('Network error','err'); return {ok:false,error:'network'}; });
  };

  /* Declarative buttons: data-api="plugin.action" data-payload='{"project_id":1}' */
  document.addEventListener('click', function(ev){
    var b = ev.target.closest('[data-api]');
    if(!b) return;
    ev.preventDefault();
    if(b.dataset.confirm && !confirm(b.dataset.confirm)) return;
    var payload = {};
    try{ payload = b.dataset.payload ? JSON.parse(b.dataset.payload) : {}; }catch(e){ payload = {}; }
    // collect sibling form fields
    var form = b.closest('[data-form]');
    if(form){ form.querySelectorAll('input,select,textarea').forEach(function(i){ if(i.name) payload[i.name]=i.value; }); }
    var original = b.innerHTML;
    b.disabled = true; b.innerHTML = '<span class="spin"></span> Working…';
    sfApi(b.dataset.api, payload).then(function(res){
      b.disabled=false; b.innerHTML=original;
      if(res && res.ok){
        if(res.message) toast(res.message,'ok');
        if(b.dataset.reload==='1' || (res.reload)) { setTimeout(function(){ location.reload(); }, 500); }
        if(b.dataset.goto) { location.href = b.dataset.goto; }
        document.dispatchEvent(new CustomEvent('sf:done',{detail:res}));
      }
    });
  });

  /* Toggle: data-toggle="switch" posts to admin */
  document.addEventListener('click', function(ev){
    var s = ev.target.closest('.switch[data-api]');
    if(!s) return;
    ev.preventDefault();
    s.classList.toggle('on');
    sfApi(s.dataset.api, s.dataset.payload ? JSON.parse(s.dataset.payload) : {});
  });

  /* Mobile sidebar */
  var burger = document.getElementById('burger');
  if(burger){ burger.addEventListener('click', function(){ document.getElementById('sb').classList.toggle('show'); }); }

  /* Job polling: refresh active job list every 6s when a page has [data-jobpoll] */
  if(document.querySelector('[data-jobpoll]')){
    setInterval(function(){
      var box = document.querySelector('[data-jobpoll]');
      if(!box) return;
      fetch(BASE + 'index.php?r=jobs&format=json', {credentials:'same-origin'})
        .then(function(r){return r.json();}).then(function(j){
          if(j && j.html){ box.innerHTML = j.html; }
          if(j && j.active === 0 && box.dataset.autoreload === '1'){ location.reload(); }
        }).catch(function(){});
    }, 6000);
  }

  /* Auto-dismiss flashes */
  setTimeout(function(){
    document.querySelectorAll('.flash').forEach(function(f){
      setTimeout(function(){ f.style.transition='.3s'; f.style.opacity=0; setTimeout(function(){f.remove();},320); }, 4000);
    });
  }, 100);

  /* Textarea autosize */
  document.addEventListener('input', function(ev){
    var t = ev.target;
    if(t.tagName === 'TEXTAREA' && t.hasAttribute('data-autosize')){
      t.style.height='auto'; t.style.height = (t.scrollHeight+2)+'px';
    }
  });
})();
