/**
 * SportsSync Chatbot — chatbot.js (v4 — Definitive)
 * public/chatbot.js  |  Vanilla JS only.
 */
(function () {
  'use strict';

  /* ── CONFIG ───────────────────────────────── */
  var API_URL        = '/chatbot_api.php';
  var SPAM_THRESHOLD = 4;
  var SPAM_WINDOW    = 2000;
  var IDLE_MIN       = 22000;
  var IDLE_MAX       = 42000;

  /* ── STATE ────────────────────────────────── */
  var lang            = localStorage.getItem('cb_lang') || null;
  var isOpen          = false;
  var isTyping        = false;
  var clickCount      = 0;
  var clickResetTimer = null;
  var spamLevel       = 0;
  var isResting       = false;
  var restTimer       = null;
  var currentMood     = 'normal';
  var moodTimer       = null;
  var idleTimer       = null;
  var toastVisible    = false;
  var toastHideTimer  = null;
  var lastInteraction = Date.now();
  var idleFaceIdx     = 0;
  var idleFaceTimer   = null;
  /* Trivia state */
  var triviaActive    = false;   // is a quiz round running?
  var triviaAnswer    = null;    // correct answer key for current Q
  var triviaScore     = 0;
  var triviaTotal     = 0;

  /* ── DOM ──────────────────────────────────── */
  var trigger, face, chatWindow, closeBtn,
      langScreen, chatScreen, messages,
      input, sendBtn, resetLangBtn, badge,
      avatarFace, headerEl, moodBar, toastEl;

  /* ── FACES ────────────────────────────────── */
  var IDLE_FACES = ['😏','😎','🤖','⚡','👀','😆','😒','🧐'];

  var MOOD_META = {
    normal:  { cls:'cb-mood-normal',  bar:'mood-normal',  face:'😏', avatarFace:'😏' },
    happy:   { cls:'cb-mood-happy',   bar:'mood-happy',   face:'😄', avatarFace:'😄' },
    annoyed: { cls:'cb-mood-annoyed', bar:'mood-annoyed', face:'😤', avatarFace:'😤' },
    sleepy:  { cls:'cb-mood-sleepy',  bar:'mood-sleepy',  face:'😴', avatarFace:'😴' },
    curious: { cls:'cb-mood-curious', bar:'mood-curious', face:'👀', avatarFace:'👀' },
    resting: { cls:'cb-mood-annoyed', bar:'mood-annoyed', face:'😮‍💨', avatarFace:'😮‍💨' },
  };
  var ALL_MOOD_CLASSES   = ['cb-mood-normal','cb-mood-happy','cb-mood-annoyed','cb-mood-sleepy','cb-mood-curious'];
  var ALL_BAR_CLASSES    = ['mood-normal','mood-happy','mood-annoyed','mood-sleepy','mood-curious'];

  var MOOD_STATUS = {
    normal:  { en:'Online — Laging handa! 😎',   tl:'Online — Laging handa! 😎'  },
    happy:   { en:'Excited mode ON! 🔥',           tl:'Excited mode ON! 🔥'         },
    annoyed: { en:'Medyo naiinis… 😤',             tl:'Medyo naiinis na… 😤'        },
    sleepy:  { en:'Antok mode… 😴',               tl:'Antok na… 😴'               },
    curious: { en:'May tanong ka ba? 🤔',          tl:'May tanong ka ba? 🤔'        },
    resting: { en:'Pahinga muna... 😮‍💨',           tl:'Pahinga muna... 😮‍💨'        },
  };

  /* ── RESPONSES ────────────────────────────── */
  var SPAM_0 = {
    en:['HOY! 😤','ANU BA?! 😤','Hey! Calm down! 😅'],
    tl:['HOY! 😤','ANU BA?! 😤','Huy! Tahan ka! 😅'],
  };
  var SPAM_1 = {
    en:['ANG KULIT MO 😭','Sige pa, pindot pa more 😒','MAGPAHINGA KA MUNA! 💀','Grabe ka non-stop! 😤'],
    tl:['ANG KULIT MO 😭','Sige pa, pindot pa more 😒','MAGPAHINGA KA NAMAN! 💀','Grabe ka talaga! 😤'],
  };
  var SPAM_2 = {
    en:['🚨 STOP IT! 🚨','OK. HINDI NA KITA SASAGUTIN. 😤','Fine. FINEEEE. 😭😤'],
    tl:['🚨 TIGILIN MO NA! 🚨','OK. HINDI NA KITA SASAGUTIN. 😤','Sige lang. SIGEEEE. 😭😤'],
  };
  var REST_MSG  = { en:'Wait lang! Pahinga muna ako sandali... 😮‍💨', tl:'Wait lang ha... pahinga muna ako. 😮‍💨' };
  var BACK_MSG  = { en:'OK na! 😤➡️😏 Back na ako. Ano kailangan mo?', tl:'OK na ko! 😤➡️😏 Nandito na ulit, boss!' };
  var GREETINGS = {
    en:["Yo! 👋 I'm **SyncBot**! Ask me about matches, players, or standings! 😎","Uy, dumating ka! 🎉 I got you, boss! ⚡","Hoy! Welcome! 👋 Anong kailangan mo? 😏"],
    tl:["Hoy! 👋 Ako si **SyncBot**! Tanong na! 😎","Uy, dumating ka! 🎉 Anong kailangan mo? 😏","Boss! Nandito na ako! ⚡ Tanong ka na! 🔥"],
  };
  var FALLBACKS = {
    en:["Hmm… di ko gets 🤔 Try: *player [name]*, *standings*, *latest match*, or *help*","Ay sus, ano daw? 😂 Type *help*!","Nani?? 👀 Type *help*!"],
    tl:["Hala, hindi ko gets 🤔 Subukan: *player [pangalan]*, *standings*, o *help*","Ay nako, ano daw?! 😂 Type *help*!","Nani?? 👀 I-type ang *help*!"],
  };
  var NO_DATA = {
    en:["Wala akong makitang data… 😭 Baka typo?","404: Not found! 💀 Check the spelling.","Hmm, empty talaga 🤷"],
    tl:["Wala akong makitang data… 😭 Baka typo?","404: Not found! 💀 Tsek ang spelling.","Hmm, wala talaga 🤷"],
  };
  var HELP_TEXT = {
    en:"Here's what I can do! 😎\n\n🎮 **Trivia** — \"trivia\" or \"quiz me\"\n📖 **Rules** — \"rules\" or \"basketball rules\"\n🔍 **Player** — \"player Juan\"\n👥 **Team** — \"team Red Warriors\"\n🏆 **Standings** — \"standings\"\n⚽ **Latest** — \"latest match\"\n🏀 **Basketball** — \"basketball results\"\n🏐 **Volleyball** — \"volleyball results\"\n🏸 **Badminton** — \"badminton results\"\n🏓 **Table Tennis** — \"table tennis results\"\n🎯 **Darts** — \"darts results\"",
    tl:"Eto ang kaya ko! 😎\n\n🎮 **Trivia** — \"trivia\" o \"quiz me\"\n📖 **Rules** — \"rules\" o \"basketball rules\"\n🔍 **Player** — \"player Juan\"\n👥 **Team** — \"team Red Warriors\"\n🏆 **Standings** — \"standings\"\n⚽ **Latest** — \"latest match\"\n🏀 **Basketball** — \"basketball results\"\n🏐 **Volleyball** — \"volleyball results\"\n🏸 **Badminton** — \"badminton results\"\n🏓 **Table Tennis** — \"table tennis results\"\n🎯 **Darts** — \"darts results\"",
  };
  var IDLE_TOASTS = {
    en:['Uy, buhay ka pa pala 😏','Ask me about players! 😆','Wala ka bang ginagawa? 😭','May bagong match results! 👀'],
    tl:['Uy, buhay ka pa pala 😏','Subukan mo akong tanungin 😆','Wala ka bang gagawin? 😭','May bagong results na! 👀'],
  };
  var IDLE_CHAT = {
    en:['Uy, nandyan ka pa! Kausapin mo naman ako 😏','Try: "latest match" or "standings" 😆','Hoy… hello?? 👋'],
    tl:['Uy, nandito ka pa! Kausapin mo naman ako 😏','I-try: "latest match" o "standings" 😆','Hoy… hello?? 👋'],
  };

  /* ── UTILS ────────────────────────────────── */
  function rand(arr){ return arr[Math.floor(Math.random()*arr.length)]; }
  function gl(){ return lang||'en'; }
  function mdToHtml(t){
    return t.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
            .replace(/\*(.+?)\*/g,'<em>$1</em>')
            .replace(/\n/g,'<br>');
  }
  function scrollBottom(){ if(messages) messages.scrollTop=messages.scrollHeight; }

  /* ── MOOD ─────────────────────────────────── */
  function setMood(mood, durationMs){
    if(!MOOD_META[mood]) mood='normal';
    currentMood=mood;
    var m=MOOD_META[mood];
    if(trigger){
      ALL_MOOD_CLASSES.forEach(function(c){ trigger.classList.remove(c); });
      trigger.classList.add(m.cls);
    }
    if(avatarFace) avatarFace.textContent=m.avatarFace;
    if(headerEl){
      headerEl.setAttribute('data-mood',mood);
      var st=headerEl.querySelector('.cb-header-status');
      if(st){
        var dot=st.querySelector('.cb-status-dot');
        var txt=MOOD_STATUS[mood][gl()];
        st.innerHTML='';
        if(dot) st.appendChild(dot);
        st.appendChild(document.createTextNode(' '+txt));
      }
    }
    if(moodBar){
      ALL_BAR_CLASSES.forEach(function(c){ moodBar.classList.remove(c); });
      moodBar.classList.add(m.bar);
    }
    if(chatWindow) chatWindow.classList.toggle('cb-window-annoyed',mood==='annoyed'||mood==='resting');
    if(moodTimer) clearTimeout(moodTimer);
    if(durationMs && mood!=='normal'){
      moodTimer=setTimeout(function(){ setMood('normal'); },durationMs);
    }
  }

  /* ── TRIGGER FACE ─────────────────────────── */
  function setFace(emoji){
    if(face) face.textContent=emoji;
  }

  /* ── IDLE FACE ROTATION ───────────────────── */
  function scheduleFaceRotation(){
    clearTimeout(idleFaceTimer);
    idleFaceTimer=setTimeout(function(){
      if(!isOpen && !isResting){
        idleFaceIdx=(idleFaceIdx+1)%IDLE_FACES.length;
        setFace(IDLE_FACES[idleFaceIdx]);
      }
      scheduleFaceRotation();
    }, 5000+Math.random()*4000);
  }

  /* ── TOAST ────────────────────────────────── */
  function showToast(text, type, ms){
    if(!toastEl) return;
    ms=ms||4000;
    clearTimeout(toastHideTimer);
    toastEl.innerHTML=mdToHtml(text);
    toastEl.className='cb-toast-show cb-toast-'+(type||'idle');
    toastVisible=true;
    /* CRITICAL: toast must NEVER intercept clicks on the trigger.
       We use pointer-events:none always and handle click via
       a transparent overlay approach instead. */
    toastEl.style.pointerEvents='none';
    toastHideTimer=setTimeout(hideToast, ms);
  }

  function hideToast(){
    if(!toastEl) return;
    clearTimeout(toastHideTimer);
    toastEl.classList.remove('cb-toast-show');
    toastEl.style.pointerEvents='none';
    toastVisible=false;
  }

  /* ── SPARKS ───────────────────────────────── */
  function burstSparks(n,colors){
    if(!trigger) return;
    var r=trigger.getBoundingClientRect();
    var cx=r.left+r.width/2, cy=r.top+r.height/2;
    for(var i=0;i<n;i++){
      (function(){
        var s=document.createElement('div');
        s.className='cb-spark';
        var a=Math.random()*Math.PI*2, d=40+Math.random()*60, sz=5+Math.random()*7;
        var c=colors[Math.floor(Math.random()*colors.length)], dl=Math.random()*120;
        s.style.cssText='left:'+(cx-sz/2)+'px;top:'+(cy-sz/2)+'px;width:'+sz+'px;height:'+sz+'px;'
          +'background:'+c+';--sx:'+(Math.cos(a)*d)+'px;--sy:'+(Math.sin(a)*d)+'px;'
          +'animation-delay:'+dl+'ms;pointer-events:none;';
        document.body.appendChild(s);
        setTimeout(function(){ s.remove(); },800+dl);
      })();
    }
  }

  function addRipple(){
    if(!trigger) return;
    var old=trigger.querySelector('.cb-ripple');
    if(old) old.remove();
    var r=document.createElement('span');
    r.className='cb-ripple';
    trigger.appendChild(r);
    setTimeout(function(){ r.remove(); },600);
  }

  /* ── WINDOW OPEN / CLOSE ──────────────────── */
  function openWindow(){
    if(isOpen) return;
    isOpen=true;
    chatWindow.classList.add('cb-open');
    setFace('✕');
    badge.classList.remove('visible');
    hideToast();
    localStorage.setItem('cb_opened','1');
    if(!lang){
      showLangScreen();
    } else {
      showChatScreen();
      if(messages.children.length===0){
        setMood('happy',3000);
        setTimeout(function(){
          appendMsg(rand(GREETINGS[lang]),'bot',
            makeChips(gl()==='tl'
              ?['Standings','Latest match','📖 Rules','🎮 Trivia','Help']
              :['Standings','Latest match','📖 Rules','🎮 Trivia','Help']));
        },400);
      }
      setTimeout(function(){ if(input) input.focus(); },100);
    }
  }

  function closeWindow(){
    if(!isOpen) return;
    isOpen=false;
    chatWindow.classList.remove('cb-open');
    setFace(IDLE_FACES[idleFaceIdx]);
  }

  function toggleWindow(){
    if(isOpen) closeWindow(); else openWindow();
  }

  /* ── SPAM REACTION ────────────────────────── */
  function flashWindow(){
    if(!chatWindow) return;
    chatWindow.classList.add('cb-flash-annoyed');
    setTimeout(function(){ chatWindow.classList.remove('cb-flash-annoyed'); },700);
  }

  function triggerSpamReaction(){
    addRipple();
    if(spamLevel===0){
      trigger.classList.add('cb-shake');
      setTimeout(function(){ trigger.classList.remove('cb-shake'); },450);
      setMood('annoyed',3000);
      burstSparks(8,['#FF8C00','#FFD700','#fff']);
      var r0=rand(SPAM_0[gl()]);
      showToast(r0,'annoyed',3000);
      if(isOpen) appendMsg(r0,'bot',null,true);
      spamLevel=1;
    } else if(spamLevel===1){
      trigger.classList.add('cb-annoyed-pop');
      setTimeout(function(){ trigger.classList.remove('cb-annoyed-pop'); },400);
      setMood('annoyed',5000);
      burstSparks(14,['#FF3B30','#FF8C00','#FFD700']);
      var r1=rand(SPAM_1[gl()]);
      showToast(r1,'annoyed',4500);
      if(isOpen){ appendMsg(r1,'bot',null,true); flashWindow(); }
      spamLevel=2;
    } else {
      if(isResting) return;
      trigger.classList.add('cb-shake');
      setTimeout(function(){ trigger.classList.remove('cb-shake'); },500);
      setMood('resting');
      burstSparks(20,['#FF3B30','#ff6b35','#FFD700','#fff']);
      var r2=rand(SPAM_2[gl()]);
      showToast(r2,'annoyed',3000);
      if(isOpen){ appendMsg(r2,'bot',null,true); flashWindow(); }
      setTimeout(function(){
        var restTxt=REST_MSG[gl()];
        showToast(restTxt,'annoyed',5500);
        if(isOpen) appendMsg(restTxt,'bot',null,true);
        setFace('😮‍💨');
      },800);
      isResting=true;
      clearTimeout(restTimer);
      restTimer=setTimeout(function(){
        isResting=false; spamLevel=0;
        setMood('normal');
        idleFaceIdx=0;
        setFace(IDLE_FACES[0]);
        var backTxt=BACK_MSG[gl()];
        showToast(backTxt,'idle',4000);
        if(isOpen) appendMsg(backTxt,'bot');
      },5000);
    }
  }

  /* ── CLICK HANDLER ────────────────────────── */
  function handleTriggerClick(e){
    e.stopPropagation();  /* prevent document click handler from also firing */
    lastInteraction=Date.now();
    hideToast();
    addRipple();
    clickCount++;
    clearTimeout(clickResetTimer);
    clickResetTimer=setTimeout(function(){ clickCount=0; },SPAM_WINDOW);
    if(clickCount>=SPAM_THRESHOLD){
      clickCount=0;
      triggerSpamReaction();
      return;
    }
    toggleWindow();
  }

  /* ── IDLE ─────────────────────────────────── */
  function resetIdleTimer(){
    clearTimeout(idleTimer);
    idleTimer=setTimeout(fireIdle, IDLE_MIN+Math.random()*(IDLE_MAX-IDLE_MIN));
  }
  function fireIdle(){
    var elapsed=Date.now()-lastInteraction;
    if(elapsed>90000) setMood('sleepy',20000);
    else setMood('curious',8000);
    if(isOpen&&lang) appendMsg(rand(IDLE_CHAT[gl()]),'bot');
    else showToast(rand(IDLE_TOASTS[gl()]),'idle',5000);
    resetIdleTimer();
  }

  /* ── LANGUAGE ─────────────────────────────── */
  function showLangScreen(){
    langScreen.classList.remove('cb-hidden');
    chatScreen.classList.add('cb-hidden');
  }
  function showChatScreen(){
    langScreen.classList.add('cb-hidden');
    chatScreen.classList.remove('cb-hidden');
    if(input) input.focus();
  }
  function selectLang(l){
    lang=l;
    localStorage.setItem('cb_lang',l);
    showChatScreen();
    setMood('happy',4000);
    setTimeout(function(){
      appendMsg(rand(GREETINGS[l]),'bot',
        makeChips(l==='tl'
          ?['Standings','Latest match','Hanapin player','Help']
          :['Standings','Latest match','Find player','Help']));
    },400);
    lastInteraction=Date.now();
    resetIdleTimer();
  }

  /* ── MESSAGES ─────────────────────────────── */
  function appendMsg(text,who,extra,annoyed){
    var d=document.createElement('div');
    d.className='cb-msg cb-msg-'+who;
    if(annoyed) d.classList.add('cb-msg-annoyed');
    d.innerHTML=mdToHtml(text);
    if(extra) d.appendChild(extra);
    messages.appendChild(d);
    scrollBottom();
    return d;
  }
  function showTyping(){
    isTyping=true;
    if(sendBtn) sendBtn.disabled=true;
    var d=document.createElement('div');
    d.className='cb-typing'; d.id='cb-typing-indicator';
    d.innerHTML='<span></span><span></span><span></span>';
    messages.appendChild(d); scrollBottom();
  }
  function hideTyping(){
    isTyping=false;
    if(sendBtn) sendBtn.disabled=false;
    var d=document.getElementById('cb-typing-indicator');
    if(d) d.remove();
  }
  function botReply(text,extra,delay,annoyed){
    delay=(delay!==undefined)?delay:(700+Math.random()*600);
    showTyping();
    setTimeout(function(){ hideTyping(); appendMsg(text,'bot',extra,annoyed); },delay);
  }
  function makeCard(title,rows){
    var c=document.createElement('div'); c.className='cb-card';
    var t=document.createElement('div'); t.className='cb-card-title';
    t.textContent=title; c.appendChild(t);
    rows.forEach(function(r){
      var row=document.createElement('div'); row.className='cb-card-row';
      row.innerHTML='<span>'+r[0]+'</span><span>'+r[1]+'</span>';
      c.appendChild(row);
    });
    return c;
  }
  function makeChips(labels){
    var w=document.createElement('div'); w.className='cb-chips';
    labels.forEach(function(lbl){
      var b=document.createElement('button'); b.className='cb-chip';
      b.textContent=lbl;
      b.addEventListener('click',function(){
        // Route trivia sport chips directly so they bypass intent detection
        var t = lbl.toLowerCase();
        if(t.indexOf('basketball') > -1 || t.indexOf('🏀') > -1) { handleUserMessage(lbl); }
        else if(t.indexOf('volleyball') > -1 || t.indexOf('🏐') > -1) { handleUserMessage(lbl); }
        else if(t.indexOf('badminton') > -1 || t.indexOf('🏸') > -1) { handleUserMessage(lbl); }
        else if(t.indexOf('table tennis') > -1 || t.indexOf('🏓') > -1) { handleUserMessage(lbl); }
        else if(t.indexOf('darts') > -1 || t.indexOf('🎯') > -1) { handleUserMessage(lbl); }
        else { handleUserMessage(lbl); }
      });
      w.appendChild(b);
    });
    return w;
  }

  /* ── INTENT ───────────────────────────────── */
  var INTENTS=[
    {p:/\bhelp\b|commands|ano.*kaya mo|what can you do/i, i:'help'},
    {p:/\bplayer\b|manlalaro|sino.*player|player.*sino/i, i:'player'},
    {p:/\bteam\b|koponan|grupo/i,                          i:'team'},
    {p:/stand(ing)?s?|leaderboard|ranking|top team/i,      i:'standings'},
    {p:/latest|recent|last.*match|pinakabago|result/i,     i:'latest'},
    {p:/basketball|bball/i,                                i:'sport_bb'},
    {p:/volleyball|volley/i,                               i:'sport_vb'},
    {p:/badminton/i,                                       i:'sport_bd'},
    {p:/table.*tennis|pingpong|ping.*pong/i,               i:'sport_tt'},
    {p:/darts?/i,                                          i:'sport_dr'},
    {p:/\bhello\b|\bhi\b|\bhey\b|kumusta|kamusta/i,        i:'greet'},
    {p:/thank|salamat|ty\b/i,                              i:'thanks'},
    {p:/\btrivia\b|quiz|tanong mo|test me|mag-quiz|subukan mo/i, i:'trivia'},
    {p:/trivia.*basketball|basketball.*trivia|bball.*quiz/i,   i:'trivia_bb'},
    {p:/trivia.*volleyball|volleyball.*trivia|volley.*quiz/i,  i:'trivia_vb'},
    {p:/trivia.*badminton|badminton.*trivia/i,                 i:'trivia_bd'},
    {p:/trivia.*table|table.*trivia|pingpong.*trivia/i,        i:'trivia_tt'},
    {p:/trivia.*darts|darts.*trivia/i,                         i:'trivia_dr'},
    {p:/rules?.*basketball|basketball.*rules?|how.*play.*basketball/i, i:'rules_bb'},
    {p:/rules?.*volleyball|volleyball.*rules?|how.*play.*volleyball/i, i:'rules_vb'},
    {p:/rules?.*badminton|badminton.*rules?|how.*play.*badminton/i,    i:'rules_bd'},
    {p:/rules?.*table|table.*rules?|rules?.*ping|ping.*rules?/i,       i:'rules_tt'},
    {p:/rules?.*darts?|darts?.*rules?|how.*play.*darts?/i,             i:'rules_dr'},
    {p:/\brules?\b|how.*play|paano.*laro|alituntunin/i,                i:'rules'},
  ];
  function detectIntent(text){
    for(var k=0;k<INTENTS.length;k++) if(INTENTS[k].p.test(text)) return INTENTS[k].i;
    return 'unknown';
  }
  function extractAfter(text,kws){
    for(var k=0;k<kws.length;k++){
      var m=text.match(new RegExp(kws[k]+'\\s+(.+)','i'));
      if(m&&m[1].trim().length>1) return m[1].trim();
    }
    return null;
  }

  /* ── API ──────────────────────────────────── */
  function callApi(params){
    var qs=Object.keys(params).map(function(k){ return encodeURIComponent(k)+'='+encodeURIComponent(params[k]); }).join('&');
    return fetch(API_URL+'?'+qs,{credentials:'same-origin'}).then(function(r){ return r.json(); });
  }

  /* ── INTENT HANDLERS ──────────────────────── */
  function handlePlayer(query){
    var name=extractAfter(query,['player','manlalaro','sino si','sino ang']);
    if(!name){ botReply(gl()==='tl'?'Anong pangalan? I-type: **player [pangalan]** 😊':'Which player? Type: **player [name]** 😊'); return; }
    callApi({action:'player',name:name}).then(function(data){
      if(!data||data.error||!data.player){ botReply(rand(NO_DATA[gl()])); return; }
      var p=data.player;
      var rows=[[gl()==='tl'?'Buong pangalan':'Full Name',p.full_name],[gl()==='tl'?'Koponan':'Team',p.team_name||'—']];
      if(p.sport) rows.push(['Sport',p.sport]);
      if(p.games_played) rows.push([gl()==='tl'?'Laro':'Games',p.games_played]);
      if(data.stats){ var s=data.stats; if(s.pts!==undefined) rows.push(['PTS',s.pts]); if(s.reb!==undefined) rows.push(['REB',s.reb]); if(s.ast!==undefined) rows.push(['AST',s.ast]); }
      setMood('happy',3000);
      botReply(gl()==='tl'?'Nahanap ko siya! 🎉 Info ni **'+p.full_name+'**:':'Found them! 🎉 Info for **'+p.full_name+'**:',makeCard('🏅 '+p.full_name,rows));
    }).catch(function(){ botReply(rand(FALLBACKS[gl()])); });
  }
  function handleTeam(query){
    var name=extractAfter(query,['team','koponan','grupo']);
    if(!name){ botReply(gl()==='tl'?'Anong team? I-type: **team [pangalan]** 😊':'Which team? Type: **team [name]** 😊'); return; }
    callApi({action:'team',name:name}).then(function(data){
      if(!data||data.error||!data.players||!data.players.length){ botReply(rand(NO_DATA[gl()])); return; }
      var lines=data.players.map(function(p){ return p.full_name; }).join(', ');
      setMood('happy',3000);
      botReply(gl()==='tl'?'Nahanap ko ang **'+data.team_name+'**! 🔥':'Found **'+data.team_name+'**! 🔥',makeCard('👥 '+data.team_name,[[gl()==='tl'?'Bilang':'Players',data.players.length],['Roster',lines]]));
    }).catch(function(){ botReply(rand(FALLBACKS[gl()])); });
  }
  function handleStandings(){
    callApi({action:'standings'}).then(function(data){
      if(!data||data.error||!data.standings||!data.standings.length){ botReply(rand(NO_DATA[gl()])); return; }
      var rows=data.standings.slice(0,8).map(function(s,i){ return [(i+1)+'. '+s.team_name,s.wins+'W – '+s.losses+'L']; });
      setMood('happy',3000);
      botReply(gl()==='tl'?'🏆 Eto ang standings! Sino kaya? 🤔':'🏆 Current standings! Who takes the crown? 🤔',makeCard('📊 Standings',rows));
    }).catch(function(){ botReply(rand(FALLBACKS[gl()])); });
  }
  function handleLatest(sport){
    var params={action:'latest'}; if(sport) params.sport=sport;
    callApi(params).then(function(data){
      if(!data||data.error||!data.matches||!data.matches.length){ botReply(rand(NO_DATA[gl()])); return; }
      var rows=data.matches.slice(0,5).map(function(m){
        var sc=(m.team_a_score!==undefined&&m.team_a_score!==null)?m.team_a_name+' '+m.team_a_score+' – '+m.team_b_score+' '+m.team_b_name:m.team_a_name+' vs '+m.team_b_name;
        return [m.sport||'Basketball',sc+(m.winner?' 🏆 '+m.winner:'')];
      });
      setMood('happy',3000);
      botReply(gl()==='tl'?'🔥 Pinakabagong laro!':'🔥 Latest match results!',makeCard('📋 Recent Matches',rows));
    }).catch(function(){ botReply(rand(FALLBACKS[gl()])); });
  }

  /* ── MAIN HANDLER ─────────────────────────── */

  /* ════════════════════════════════════════════
     TRIVIA SYSTEM
  ════════════════════════════════════════════ */

  var TRIVIA_BANK = {
    basketball: [
      { q: 'Ilang players ang nasa court per team sa basketball? 🏀',
        opts: { a:'4', b:'5', c:'6', d:'3' }, ans: 'b',
        fact_en: 'Basketball is 5v5! That is why it is called the "starting five" 😎',
        fact_tl: '5 players per team! Kaya tinatawag na "starting five" 😎' },
      { q: 'Sino ang nag-imbento ng basketball? 🤔',
        opts: { a:'Michael Jordan', b:'LeBron James', c:'James Naismith', d:'Kobe Bryant' }, ans: 'c',
        fact_en: 'James Naismith invented it in 1891 using a peach basket. A PEACH BASKET. 😭',
        fact_tl: 'James Naismith! Noong 1891 gamit ang peach basket. PEACH BASKET. 😭' },
      { q: 'Ilang minuto ang isang quarter sa NBA? ⏱️',
        opts: { a:'10 mins', b:'8 mins', c:'15 mins', d:'12 mins' }, ans: 'd',
        fact_en: 'NBA quarters are 12 minutes each. FIBA uses 10. Do not mix them up! 😤',
        fact_tl: 'NBA quarters ay 12 minuto. FIBA ay 10. Huwag malito! 😤' },
      { q: 'Ilang puntos ang worth ng isang three-pointer? 🎯',
        opts: { a:'2', b:'3', c:'4', d:'1' }, ans: 'b',
        fact_en: 'Three points! That is why it is called a three-pointer... genius naming right? 😏',
        fact_tl: 'Tatlong puntos! Kaya tinatawag na three-pointer... obvious no? 😏' },
      { q: 'Anong taon naging Olympic sport ang basketball? 🥇',
        opts: { a:'1904', b:'1936', c:'1952', d:'1948' }, ans: 'b',
        fact_en: 'Basketball became an Olympic sport in 1936 Berlin. USA won gold. Surprise. 😒',
        fact_tl: 'Naging Olympic sport noong 1936 sa Berlin. USA nanalo. Aba diba. 😒' },
      { q: 'Sa FIBA rules, ilang fouls bago ma-foul out ang isang player? 😤',
        opts: { a:'4', b:'6', c:'5', d:'3' }, ans: 'c',
        fact_en: '5 personal fouls under FIBA rules — and you are OUT. No more playing! 😤',
        fact_tl: '5 personal fouls sa FIBA rules — labas ka na! Wala nang laro para sa iyo! 😤' },
      { q: 'Ilang segundo ang shot clock sa standard basketball? ⏱️',
        opts: { a:'30 sec', b:'24 sec', c:'20 sec', d:'14 sec' }, ans: 'b',
        fact_en: '24 seconds! A team must attempt a shot within this time or it\'s a violation. Tick tock! ⏱️',
        fact_tl: '24 segundo! Dapat mag-shoot ang team sa loob nito o violation. Tick tock! ⏱️' },
    ],
    volleyball: [
      { q: 'Ilang players ang nasa court per team sa volleyball? 🏐',
        opts: { a:'5', b:'7', c:'6', d:'4' }, ans: 'c',
        fact_en: '6 players! And they rotate positions every time they win a serve. Confusing but fun! 😆',
        fact_tl: '6 players! At nag-iikot sila ng posisyon. Nakakalito pero masaya! 😆' },
      { q: 'Ilang sets ang kailangan panalunan sa isang volleyball match? 🏆',
        opts: { a:'2', b:'5', c:'4', d:'3' }, ans: 'd',
        fact_en: 'Best of 5 sets, first to win 3! The 5th set is only up to 15 points too 😏',
        fact_tl: 'Best of 5 sets, una sa 3 panalo! Yung 5th set 15 points lang 😏' },
      { q: 'Sino ang nag-imbento ng volleyball? 🤔',
        opts: { a:'James Naismith', b:'William Morgan', c:'John Smith', d:'Mike Volley' }, ans: 'b',
        fact_en: 'William Morgan invented it in 1895. He called it "Mintonette." Yes, MINTONETTE. 😭',
        fact_tl: 'William Morgan noong 1895. Pinangalanan niya itong "Mintonette." OO. MINTONETTE. 😭' },
      { q: 'Ilang puntos ang kailangan para manalo ng isang set? (non-deciding)',
        opts: { a:'21', b:'15', c:'25', d:'30' }, ans: 'c',
        fact_en: '25 points! But you need to win by 2, so it can go to 26-24, 27-25... forever 😅',
        fact_tl: '25 points! Pero kailangan ng 2-point lead. 26-24, 27-25... walang katapusan 😅' },
      { q: 'Anong volleyball position ang hindi pwedeng mag-spike o mag-block sa net?',
        opts: { a:'Setter', b:'Libero', c:'Outside Hitter', d:'Middle Blocker' }, ans: 'b',
        fact_en: 'The Libero! They wear a different jersey and have special rules. Secret agent vibes 🕵️',
        fact_tl: 'Ang Libero! Nag-iiba ng jersey at may special rules. Secret agent vibes 🕵️' },
      { q: 'Sa volleyball, ilang touches ang allowed bawat team bago ibalik ang bola? ✋',
        opts: { a:'2', b:'4', c:'3', d:'5' }, ans: 'c',
        fact_en: '3 touches maximum! Usually: receive → set → spike. Classic combo! 🏐',
        fact_tl: '3 touches maximum! Karaniwan: receive → set → spike. Classic combo! 🏐' },
      { q: 'Ilang points ang kailangan para manalo ng deciding (5th) set sa volleyball?',
        opts: { a:'25', b:'21', c:'20', d:'15' }, ans: 'd',
        fact_en: '15 points only for the 5th set! But you still need to win by 2. Short but intense! 😤',
        fact_tl: '15 points lang sa 5th set! Pero kailangan pa rin ng 2-point lead. Maikli pero intense! 😤' },
    ],
    badminton: [
      { q: 'Ilang puntos ang kailangan para manalo ng isang badminton game?',
        opts: { a:'15', b:'11', c:'25', d:'21' }, ans: 'd',
        fact_en: '21 points! Win by 2, max 30-29. Those last few rallies are brutal 😤',
        fact_tl: '21 points! Panalo sa 2-point lead, max 30-29. Brutal yung huli 😤' },
      { q: 'Ano ang tawag sa "ball" sa badminton? 🏸',
        opts: { a:'Birdie', b:'Puck', c:'Shuttle', d:'Both A and C' }, ans: 'd',
        fact_en: 'Both "shuttlecock" and "birdie" are correct! It has FEATHERS. Real ones. 😂',
        fact_tl: '"Shuttlecock" at "birdie" pareho tama! May TUNAY na balahibo ito. 😂' },
      { q: 'Ilang games ang isang badminton match? 🏆',
        opts: { a:'1', b:'5', c:'4', d:'3' }, ans: 'd',
        fact_en: 'Best of 3 games! First to win 2 takes the match. Quick and intense 💨',
        fact_tl: 'Best of 3 games! Una sa 2 panalo ang mananalo. Mabilis at intense 💨' },
      { q: 'Ilang km/h ang pinakamabilis na recorded na badminton smash? 💨',
        opts: { a:'200 km/h', b:'306 km/h', c:'250 km/h', d:'180 km/h' }, ans: 'b',
        fact_en: '306 km/h by Mads Pieler Kolding! That is faster than some race cars. What. 😳',
        fact_tl: '306 km/h ni Mads Pieler Kolding! Mas mabilis pa sa ilang race cars. Grabe. 😳' },
      { q: 'Anong bansa ang nangunguna sa Olympic badminton gold medals?',
        opts: { a:'Japan', b:'Malaysia', c:'Indonesia', d:'China' }, ans: 'd',
        fact_en: 'China! They dominate badminton like nobody\'s business 😤 RESPECT.',
        fact_tl: 'China! Nangunguna sila sa badminton. Walang tatalo 😤 RESPEK.' },
      { q: 'Sa badminton, anong mangyayari kung 29-all ang score? 😤',
        opts: { a:'Sudden death', b:'Replay from 20', c:'Sino maka-score ng 30th point = panalo', d:'Additional game' }, ans: 'c',
        fact_en: 'At 29-all, whoever scores the 30th point wins — no more extensions! 🏸',
        fact_tl: 'Sa 29-all, sino maka-score ng 30th point ang panalo — walang dagdag na extension! 🏸' },
      { q: 'Ilang games ang kailangan panalunan para manalo ng isang badminton match? 🏆',
        opts: { a:'1', b:'3', c:'2', d:'4' }, ans: 'c',
        fact_en: 'Win 2 out of 3 games to win the match! Best of 3 format. Intense til the end! 💨',
        fact_tl: 'Panalo ng 2 sa 3 games para manalo ng match! Best of 3 format. Intense hanggang huli! 💨' },
    ],
    tabletennis: [
      { q: 'Ilang mm ang diameter ng table tennis ball? 🏓',
        opts: { a:'30mm', b:'38mm', c:'40mm', d:'45mm' }, ans: 'c',
        fact_en: '40mm since 2000! Before that it was 38mm. The extra 2mm changed pro gameplay forever 😤',
        fact_tl: '40mm mula 2000! Dati 38mm. Yung 2mm na dagdag nagbago ng lahat 😤' },
      { q: 'Ilang puntos ang kailangan para manalo ng isang table tennis game?',
        opts: { a:'15', b:'11', c:'21', d:'25' }, ans: 'b',
        fact_en: '11 points! Win by 2. Changed from 21 in 2001 to make matches faster 😏',
        fact_tl: '11 points! Panalo sa 2 point lead. Binago mula 21 noong 2001 para mabilis 😏' },
      { q: 'Anong bansa ang nanalo ng pinaka-maraming Olympic table tennis gold medals?',
        opts: { a:'Japan', b:'South Korea', c:'China', d:'Germany' }, ans: 'c',
        fact_en: 'China again!! 32 out of 37 possible gold medals. Someone stop them 😭',
        fact_tl: 'China ulit!! 32 sa 37 possible gold medals. Dapat pigilan na sila 😭' },
      { q: 'Ilang beses maaaring tumalon ang ball bago ituring na "out of play"?',
        opts: { a:'Once', b:'Twice', c:'Three times', d:'Zero - it must be hit' }, ans: 'd',
        fact_en: 'The ball must be hit before it bounces a SECOND time on your side! One bounce allowed.',
        fact_tl: 'Dapat i-hit ang bola bago ito umabot sa pangalawang bounce sa iyong side!' },
      { q: 'Ano ang tawag sa laro ng table tennis sa original nito? 🤔',
        opts: { a:'Ping Pong', b:'Whiff Whaff', c:'Click Clack', d:'Table Ball' }, ans: 'b',
        fact_en: '"Whiff-whaff"! Invented in England 1880s. The name thankfully changed. 😂',
        fact_tl: '"Whiff-whaff"! Na-imbento sa England noong 1880s. Buti na lang nabago 😂' },
      { q: 'Sa table tennis, kailan nagpapalitan ng serve tuwing isang punto? 🏓',
        opts: { a:'Lagi', b:'Sa 10-all', c:'Kapag nanalo', d:'Sa 5-all' }, ans: 'b',
        fact_en: 'At 10-all (deuce), service alternates every single point! Every rally matters. 😤',
        fact_tl: 'Sa 10-all (deuce), nagpapalitan ng serve bawat punto! Bawat rally mahalaga. 😤' },
      { q: 'Ilang points ang kailangan para manalo ng isang table tennis game? 🏓',
        opts: { a:'21', b:'15', c:'11', d:'25' }, ans: 'c',
        fact_en: '11 points — but win by 2! Changed from 21 to 11 in 2001 for faster gameplay. 😏',
        fact_tl: '11 points — pero panalo sa 2 point lead! Binago mula 21 noong 2001. 😏' },
    ],
    darts: [
      { q: 'Ilang puntos ang bullseye sa standard darts? 🎯',
        opts: { a:'25', b:'100', c:'50', d:'75' }, ans: 'c',
        fact_en: '50 points! The outer bull is 25. Aim for the middle always! 🎯',
        fact_tl: '50 points! Yung outer bull ay 25. Lagi sa gitna! 🎯' },
      { q: 'Ilang darts ang pwedeng itapon per turn sa standard play?',
        opts: { a:'2', b:'5', c:'1', d:'3' }, ans: 'd',
        fact_en: '3 darts per turn! Use them wisely. Miss all 3 and the crowd will judge you 😬',
        fact_tl: '3 darts per turn! Gamitin ng mabuti. Miss lahat ng 3 at hahatulan ka ng lahat 😬' },
      { q: 'Anong classic darts game ang nagsisimula at nagtatapos sa double? 🎯',
        opts: { a:'Cricket', b:'Around the Clock', c:'501', d:'Killer' }, ans: 'c',
        fact_en: '501! Start at 501, count down, and you must hit a double to finish. Stressful. 😅',
        fact_tl: '501! Magsimula sa 501, magbawas, at double ang kailangan para matapos. Nakakapraning 😅' },
      { q: 'Ilang segments ang may double/triple sa standard dartboard?',
        opts: { a:'10', b:'15', c:'20', d:'18' }, ans: 'c',
        fact_en: '20 segments numbered 1-20! Each has a double (outer ring) and triple (inner ring) 🎯',
        fact_tl: '20 segments numbered 1-20! Bawat isa may double (outer) at triple (inner) 🎯' },
      { q: 'Anong taon naging isang organised professional sport ang darts?',
        opts: { a:'1952', b:'1994', c:'1978', d:'1968' }, ans: 'c',
        fact_en: 'The British Darts Organisation was formed in 1973, first World Championship in 1978! 🎯',
        fact_tl: 'Ang British Darts Organisation ay nabuo noong 1973, first World Championship noong 1978! 🎯' },
      { q: 'Sa 501 darts, ano ang mangyayari kapag nag-go below 0 ang score mo? 😱',
        opts: { a:'Nananatili sa 0', b:'BUST — babalik sa dating score', c:'Katapat ang panalo', d:'Restart ang laro' }, ans: 'b',
        fact_en: 'BUST! Your score resets to what it was before that throw. So close, yet so far! 😭',
        fact_tl: 'BUST! Babalik ang score mo bago ang throw na iyon. Nakakainis! 😭' },
      { q: 'Ilang points ang outer bull sa dartboard? 🎯',
        opts: { a:'50', b:'30', c:'10', d:'25' }, ans: 'd',
        fact_en: 'Outer bull = 25 points. Bullseye (inner) = 50 points. Two different targets! 🎯',
        fact_tl: 'Outer bull = 25 points. Bullseye (inner) = 50 points. Magkaibang target! 🎯' },
    ],
  };

  var TRIVIA_INTRO = {
    en: [
      'OH? Gusto mo mag-trivia? Sige! Baka marunong ka... O baka hindi. 😏',
      'TRIVIA TIME! Handa ka na? O ikaw pa yung kailangan i-educate? 😆',
      'Ay eto na! Trivia! Pero warning: mahirap ito. Baka malungkot ka after. 😭',
    ],
    tl: [
      'OH? Gusto mo mag-trivia? Sige! Baka marunong ka... O baka hindi. 😏',
      'TRIVIA TIME! Handa ka na? O ikaw pa yung kailangan i-educate? 😆',
      'Ay eto na! Trivia! Warning: mahirap ito. Baka malungkot ka after. 😭',
    ],
  };

  var TRIVIA_CORRECT = {
    en: [
      'TAMA! Uy hindi ka pala bobo! 🎉',
      'CORRECT! Grabe ka ha, alam mo pala ito! 😲',
      'YES! Sana all ganyan ka-smart! 🏆',
      'AYOS! Hindi ka pala nagpapanggap lang! 😎',
    ],
    tl: [
      'TAMA! Uy hindi ka pala bobo! 🎉',
      'CORRECT! Grabe ka ha, alam mo pala ito! 😲',
      'YES! Sana all ganyan ka-smart! 🏆',
      'AYOS! Hindi ka nagpapanggap lang! 😎',
    ],
  };

  var TRIVIA_WRONG = {
    en: [
      'MALI! Ay nako... 😭 Sana next time!',
      'WRONG! Grabe ka talaga 😂 Pero OK lang, may fun fact pa!',
      'NOPE! Hindi yun boss... 😅 Eto ang tamang sagot:',
      'AY HINDI! Baka kailangan mo pang mag-aral 😏',
    ],
    tl: [
      'MALI! Ay nako... 😭 Sana next time!',
      'WRONG! Grabe ka talaga 😂 Pero OK lang, may fun fact pa!',
      'HINDI YAN! Boss... 😅 Eto ang tamang sagot:',
      'AY HINDI! Baka kailangan mo pang mag-aral 😏',
    ],
  };

  var TRIVIA_END = {
    en: ['Score mo: {s}/{t}! {msg}', 'Final score: {s}/{t}! {msg}'],
    tl: ['Score mo: {s}/{t}! {msg}', 'Final score: {s}/{t}! {msg}'],
  };

  var TRIVIA_END_MSG = {
    en: {
      perfect: 'PERFECT SCORE! Grabe ka! Ang galing galing mo! 🏆🔥',
      good:    'Hindi masama! Solid performance, boss! 😎',
      ok:      'Pwede na... Baka swerte lang? 😏',
      bad:     'Ay nako... Need mo mag-practice! 😭',
    },
    tl: {
      perfect: 'PERFECT SCORE! Grabe ka talaga! Ang galing! 🏆🔥',
      good:    'Hindi masama! Solid! 😎',
      ok:      'Pwede na rin... Baka swerte lang? 😏',
      bad:     'Ay nako... Kailangan mo pang mag-aral! 😭',
    },
  };

  var triviaQueue  = [];   // queued questions for current session
  var triviaOptMap = {};   // maps option letter → full text for current Q

  function getTriviaEndMsg(score, total) {
    var pct = score / total;
    var key = pct === 1 ? 'perfect' : pct >= 0.7 ? 'good' : pct >= 0.4 ? 'ok' : 'bad';
    return TRIVIA_END_MSG[gl()][key];
  }

  function startTrivia(sport) {
    var pool = TRIVIA_BANK[sport];
    if (!pool) { botReply(rand(FALLBACKS[gl()])); return; }

    triviaActive = true;
    triviaScore  = 0;
    triviaTotal  = 0;
    // Shuffle and pick up to 3 questions
    var shuffled = pool.slice().sort(function(){ return Math.random() - 0.5; });
    triviaQueue  = shuffled.slice(0, 3);

    var sportLabels = { basketball:'🏀 Basketball', volleyball:'🏐 Volleyball', badminton:'🏸 Badminton', tabletennis:'🏓 Table Tennis', darts:'🎯 Darts' };
    setMood('happy', 20000);
    var introLine = rand(TRIVIA_INTRO[gl()]);
    var catLine   = 'Category: <strong>' + sportLabels[sport] + '</strong> — '
                  + (gl()==='tl' ? '3 tanong lang. Handa ka na? 😤' : '3 questions only. Ready ka na? 😤');
    botReply(introLine, null, 500);
    setTimeout(function(){ botReply(catLine, null, 400); }, 1200);
    setTimeout(askNextTrivia, 2600);
  }

  function askNextTrivia() {
    if (triviaQueue.length === 0) {
      endTrivia(); return;
    }
    var q = triviaQueue.shift();
    triviaAnswer = q.ans;
    triviaTotal++;

    var num = triviaTotal;
    var optKeys = Object.keys(q.opts);
    triviaOptMap = {};
    optKeys.forEach(function(k){ triviaOptMap[k] = q.opts[k]; });

    // Build question message
    var qText = '<strong>Q' + num + ':</strong> ' + q.q + '<br><br>';
    optKeys.forEach(function(k){
      qText += '<span style="opacity:.7">' + k.toUpperCase() + '.</span> ' + q.opts[k] + '<br>';
    });

    showTyping();
    setTimeout(function(){
      hideTyping();
      appendMsg(qText, 'bot');
      // Show answer chips
      var chips = makeChips(optKeys.map(function(k){ return k.toUpperCase() + '. ' + q.opts[k]; }));
      // Tag each chip with its key so we can detect the answer
      chips.querySelectorAll('.cb-chip').forEach(function(btn, idx){
        var key = optKeys[idx];
        btn.dataset.triviaKey = key;
        // Override the chip click to go through trivia handler
        btn.replaceWith(btn); // need fresh listener
      });
      // Re-build chips with trivia-aware handler
      var triviaChips = document.createElement('div');
      triviaChips.className = 'cb-chips';
      optKeys.forEach(function(k){
        var b = document.createElement('button');
        b.className = 'cb-chip';
        b.textContent = k.toUpperCase() + '. ' + q.opts[k];
        b.addEventListener('click', function(){
          // Disable all chips
          triviaChips.querySelectorAll('.cb-chip').forEach(function(x){ x.classList.add('ab-used'); x.style.pointerEvents='none'; });
          handleTriviaAnswer(k, q);
        });
        triviaChips.appendChild(b);
      });
      messages.appendChild(triviaChips);
      scrollBottom();
    }, 900);
  }

  function handleTriviaAnswer(chosen, q) {
    var chosenLabel = chosen.toUpperCase() + '. ' + (q.opts[chosen] || chosen);
    appendMsg(chosenLabel, 'user');

    var isCorrect = chosen === q.ans;
    if (isCorrect) {
      triviaScore++;
      setMood('happy', 3000);
      var feedback = rand(TRIVIA_CORRECT[gl()]);
      botReply(feedback + '<br><em style="opacity:.7;font-size:.8rem;">' + (gl()==='tl'?q.fact_tl||q.fact_en:q.fact_en) + '</em>', null, 600);
    } else {
      setMood('annoyed', 3000);
      var wrongFb = rand(TRIVIA_WRONG[gl()]);
      var correctLabel = q.ans.toUpperCase() + '. ' + q.opts[q.ans];
      botReply(wrongFb + '<br><strong>' + correctLabel + '</strong><br><em style="opacity:.7;font-size:.8rem;">' + (gl()==='tl'?q.fact_tl||q.fact_en:q.fact_en) + '</em>', null, 600);
    }

    setTimeout(function(){
      if (triviaQueue.length > 0) {
        botReply(gl()==='tl'
          ? 'Sunod na tanong! Kaya mo pa ba? 😏'
          : 'Next question! Kaya mo pa? 😏', null, 400);
        setTimeout(askNextTrivia, 1800);
      } else {
        setTimeout(endTrivia, 1600);
      }
    }, 1800);
  }

  function endTrivia() {
    triviaActive = false;
    setMood('normal');
    var endMsg = rand(TRIVIA_END[gl()])
      .replace('{s}', triviaScore)
      .replace('{t}', triviaTotal)
      .replace('{msg}', getTriviaEndMsg(triviaScore, triviaTotal));

    botReply(endMsg, null, 800);
    setTimeout(function(){
      var txt = gl()==='tl'
        ? 'Gusto mo pa? Pumili ng sport! 😏'
        : 'Want more? Pick a sport! 😏';
      botReply(txt, makeTriviaMenuChips(), 400);
    }, 2200);
  }

  function makeTriviaMenuChips() {
    var sports = [
      { label:'🏀 Basketball',  key:'basketball'  },
      { label:'🏐 Volleyball',   key:'volleyball'  },
      { label:'🏸 Badminton',    key:'badminton'   },
      { label:'🏓 Table Tennis', key:'tabletennis' },
      { label:'🎯 Darts',        key:'darts'       },
    ];
    var wrap = document.createElement('div');
    wrap.className = 'cb-chips';
    sports.forEach(function(s){
      var b = document.createElement('button');
      b.className = 'cb-chip';
      b.textContent = s.label;
      b.addEventListener('click', function(){
        wrap.querySelectorAll('.cb-chip').forEach(function(x){ x.style.pointerEvents='none'; x.style.opacity='.4'; });
        appendMsg(s.label, 'user');
        startTrivia(s.key);
      });
      wrap.appendChild(b);
    });
    return wrap;
  }

  function showTriviaMenu() {
    setMood('curious', 5000);
    botReply(
      gl()==='tl'
        ? 'TRIVIA TIME! 🎮 Piliin ang sport! Baka marunong ka... O baka hindi. 😏'
        : 'TRIVIA TIME! 🎮 Pick a sport! Let us see how much you actually know. 😏',
      makeTriviaMenuChips(), 500
    );
  }


  /* ════════════════════════════════════════════
     RULES SYSTEM
  ════════════════════════════════════════════ */

  var RULES_BANK = {
    basketball: {
      icon: '🏀', label: 'Basketball',
      intro: {
        en: 'Eto na! 🏀 **Basketball Rules** — simplified para sa iyo! 😎',
        tl: 'Eto na! 🏀 **Basketball Rules** — simple at malinaw! 😎',
      },
      sections: [
        {
          title_en: '🎯 Scoring',
          title_tl: '🎯 Scoring',
          body_en:  '**Field goal** = 2 pts | **3-pointer** = 3 pts | **Free throw** = 1 pt',
          body_tl:  '**Field goal** = 2 pts | **3-pointer** = 3 pts | **Free throw** = 1 pt',
        },
        {
          title_en: '⛔ Fouls',
          title_tl: '⛔ Fouls',
          body_en:  'A foul is illegal physical contact. Under FIBA rules, **5 fouls = player is out** of the game.',
          body_tl:  'Ang foul ay illegal na contact. Sa FIBA rules, **5 fouls = player ay labas** na ng laro.',
        },
        {
          title_en: '⏱️ Shot Clock',
          title_tl: '⏱️ Shot Clock',
          body_en:  'Each team must attempt a shot within **24 seconds**. Failure = shot clock violation!',
          body_tl:  'Kailangan mag-shoot ang team sa loob ng **24 segundo**. Pag hindi = violation!',
        },
        {
          title_en: '🚫 Common Violations',
          title_tl: '🚫 Common Violations',
          body_en:  'Traveling, Double dribble, Backcourt violation, Out-of-bounds, Shot clock violation.',
          body_tl:  'Traveling, Double dribble, Backcourt violation, Out-of-bounds, Shot clock violation.',
        },
        {
          title_en: '📊 In SportSync',
          title_tl: '📊 Sa SportSync',
          body_en:  'Tracks: Live scores, fouls, timeouts, quarters, shot clock, player stats & Player of the Game! 🏆',
          body_tl:  'Tina-track: Live scores, fouls, timeouts, quarters, shot clock, player stats at Player of the Game! 🏆',
        },
      ],
    },
    volleyball: {
      icon: '🏐', label: 'Volleyball',
      intro: {
        en: 'Eto na! 🏐 **Volleyball Rules** — easy to understand! 😎',
        tl: 'Eto na! 🏐 **Volleyball Rules** — simple lang ito! 😎',
      },
      sections: [
        {
          title_en: '🎯 Scoring System',
          title_tl: '🎯 Scoring System',
          body_en:  '**Rally point system** — a point is scored after EVERY rally, regardless of who served.',
          body_tl:  '**Rally point system** — may point pagkatapos ng BAWAT rally, kahit sino ang nagserve.',
        },
        {
          title_en: '🏆 Winning a Set',
          title_tl: '🏆 Panalo sa Set',
          body_en:  'First 4 sets = **25 points** | Deciding 5th set = **15 points** | Must win by **2 points**.',
          body_tl:  'Una 4 sets = **25 points** | Panghuli 5th set = **15 points** | Kailangan ng **2-point lead**.',
        },
        {
          title_en: '✋ Touches Per Rally',
          title_tl: '✋ Touches Per Rally',
          body_en:  'Each team is allowed **up to 3 touches** before returning the ball over the net.',
          body_tl:  'Bawat team ay maaaring **mag-touch ng hanggang 3 beses** bago ibalik ang bola.',
        },
        {
          title_en: '🔄 Match Format',
          title_tl: '🔄 Match Format',
          body_en:  'Standard matches are **best of 5 sets** — first to win 3 sets wins the match!',
          body_tl:  'Standard match ay **best of 5 sets** — una sa 3 sets ang mananalo ng match!',
        },
        {
          title_en: '📊 In SportSync',
          title_tl: '📊 Sa SportSync',
          body_en:  'Tracks: Score, set progress, team records, match status & results! 🏐',
          body_tl:  'Tina-track: Score, set progress, team records, at match results! 🏐',
        },
      ],
    },
    badminton: {
      icon: '🏸', label: 'Badminton',
      intro: {
        en: 'Eto na! 🏸 **Badminton Rules** — shuttlecock knowledge incoming! 😎',
        tl: 'Eto na! 🏸 **Badminton Rules** — shuttlecock facts! 😎',
      },
      sections: [
        {
          title_en: '🎯 Scoring',
          title_tl: '🎯 Scoring',
          body_en:  'First to **21 points** wins a game. Uses **rally scoring** — point scored after every rally.',
          body_tl:  'Una sa **21 points** ang panalo sa isang game. **Rally scoring** — may point sa bawat rally.',
        },
        {
          title_en: '🏆 Match Format',
          title_tl: '🏆 Match Format',
          body_en:  '**Best of 3 games** — first to win 2 games wins the match!',
          body_tl:  '**Best of 3 games** — una sa 2 games ang mananalo ng match!',
        },
        {
          title_en: '⚠️ Deuce Rules',
          title_tl: '⚠️ Deuce Rules',
          body_en:  'At **20-all**: win by 2-point lead | At **29-all**: whoever scores 30th point wins!',
          body_tl:  'Sa **20-all**: kailangan ng 2-point lead | Sa **29-all**: sino maka-score ng 30 = panalo!',
        },
        {
          title_en: '🏸 Playing Modes',
          title_tl: '🏸 Playing Modes',
          body_en:  'Can be played as **singles** (1v1) or **doubles** (2v2). Rally ends when shuttlecock hits floor, goes out, or a fault is committed.',
          body_tl:  'Pwedeng **singles** (1v1) o **doubles** (2v2). Rally natatapos kapag nag-land sa sahig, lumabas, o may fault.',
        },
        {
          title_en: '📊 In SportSync',
          title_tl: '📊 Sa SportSync',
          body_en:  'Tracks: Score, match results, player/team records & tournament monitoring! 🏸',
          body_tl:  'Tina-track: Score, match results, player/team records at tournament monitoring! 🏸',
        },
      ],
    },
    tabletennis: {
      icon: '🏓', label: 'Table Tennis',
      intro: {
        en: 'Eto na! 🏓 **Table Tennis Rules** — formerly "Whiff-whaff." True story. 😂',
        tl: 'Eto na! 🏓 **Table Tennis Rules** — dati "Whiff-whaff" ang pangalan. Totoo. 😂',
      },
      sections: [
        {
          title_en: '🎯 Scoring',
          title_tl: '🎯 Scoring',
          body_en:  'First to **11 points** wins a game. Must win by at least **2 points**. At **10-all**: play continues until 2-point lead.',
          body_tl:  'Una sa **11 points** ang panalo. Kailangan ng hindi bababa sa **2-point lead**. Sa **10-all**: patuloy hanggang may 2-point lead.',
        },
        {
          title_en: '🔄 Service Rules',
          title_tl: '🔄 Service Rules',
          body_en:  'Players alternate serves every **2 points**. At **10-all**, service alternates every single point!',
          body_tl:  'Nagpapalitan ng serve tuwing **2 points**. Sa **10-all**, nagpapalitan ng serve bawat punto!',
        },
        {
          title_en: '🏆 Match Format',
          title_tl: '🏆 Match Format',
          body_en:  'Usually **best of 3, 5, or 7 games** depending on tournament format.',
          body_tl:  'Karaniwan ay **best of 3, 5, o 7 games** depende sa tournament format.',
        },
        {
          title_en: '🏓 How to Play',
          title_tl: '🏓 Paano Maglaro',
          body_en:  'Singles (1v1) or Doubles (2v2). Hit the ball over the net using a paddle. Opponent fails to return = your point!',
          body_tl:  'Singles (1v1) o Doubles (2v2). I-hit ang bola gamit ang paddle. Kapag hindi nakabalik ang kalaban = iyong punto!',
        },
        {
          title_en: '📊 In SportSync',
          title_tl: '📊 Sa SportSync',
          body_en:  'Tracks: Score, match results, player/team records & tournament monitoring! 🏓',
          body_tl:  'Tina-track: Score, match results, player/team records at tournament monitoring! 🏓',
        },
      ],
    },
    darts: {
      icon: '🎯', label: 'Darts',
      intro: {
        en: 'Eto na! 🎯 **Darts Rules** — aim, throw, score! Simple (but really not). 😏',
        tl: 'Eto na! 🎯 **Darts Rules** — itapon, mag-score, manalo! Simple (hindi talaga). 😏',
      },
      sections: [
        {
          title_en: '🎯 The 501 Format',
          title_tl: '🎯 Ang 501 Format',
          body_en:  'Most common format: **501**. Each player starts at 501 points, **subtracts** their score each turn. First to reach exactly **0** wins!',
          body_tl:  'Pinakakaraniwang format: **501**. Nagsisimula sa 501, **ibabawas** ang score bawat turn. Una sa **0** ang panalo!',
        },
        {
          title_en: '💥 Busting',
          title_tl: '💥 Busting',
          body_en:  'If your score goes **below 0**, that is a BUST! Your score returns to what it was before that turn.',
          body_tl:  'Kapag nag-go **below 0** ang score mo, BUST yan! Babalik ang score mo sa dati bago ang turn na iyon.',
        },
        {
          title_en: '🏹 Per Turn',
          title_tl: '🏹 Per Turn',
          body_en:  'A player throws **3 darts per turn**. Outer narrow ring = **double** | Inner narrow ring = **triple**.',
          body_tl:  'Mag-iihagis ng **3 darts per turn**. Outer narrow ring = **double** | Inner narrow ring = **triple**.',
        },
        {
          title_en: '🎯 Bullseye',
          title_tl: '🎯 Bullseye',
          body_en:  '**Bullseye** = 50 pts | **Outer bull** = 25 pts. Many 501 games require a **double-out** to finish!',
          body_tl:  '**Bullseye** = 50 pts | **Outer bull** = 25 pts. Maraming 501 games ay nangangailangan ng **double-out** para matapos!',
        },
        {
          title_en: '📊 In SportSync',
          title_tl: '📊 Sa SportSync',
          body_en:  'Tracks: Player scores, match progress & winner recording! 🎯',
          body_tl:  'Tina-track: Player scores, match progress at winner recording! 🎯',
        },
      ],
    },
  };

  function makeRulesChips() {
    var sports = [
      { label:'🏀 Basketball',  key:'basketball'  },
      { label:'🏐 Volleyball',  key:'volleyball'  },
      { label:'🏸 Badminton',   key:'badminton'   },
      { label:'🏓 Table Tennis',key:'tabletennis' },
      { label:'🎯 Darts',       key:'darts'       },
    ];
    var wrap = document.createElement('div');
    wrap.className = 'cb-chips';
    sports.forEach(function(s){
      var b = document.createElement('button');
      b.className = 'cb-chip';
      b.textContent = s.label;
      b.addEventListener('click', function(){
        wrap.querySelectorAll('.cb-chip').forEach(function(x){ x.style.pointerEvents='none'; x.style.opacity='.4'; });
        appendMsg(s.label, 'user');
        showRules(s.key);
      });
      wrap.appendChild(b);
    });
    return wrap;
  }

  function showRulesMenu() {
    setMood('curious', 5000);
    botReply(
      gl()==='tl'
        ? '📖 **Sports Rules!** Piliin ang sport na gusto mong malaman ang rules! 😎'
        : '📖 **Sports Rules!** Pick a sport to learn the rules! 😎',
      makeRulesChips(), 400
    );
  }

  function showRules(sport) {
    var data = RULES_BANK[sport];
    if (!data) { botReply(rand(FALLBACKS[gl()])); return; }
    setMood('curious', 15000);
    botReply(data.intro[gl()], null, 400);
    var delay = 1200;
    data.sections.forEach(function(sec, idx){
      var title = gl()==='tl' ? sec.title_tl : sec.title_en;
      var body  = gl()==='tl' ? sec.body_tl  : sec.body_en;
      setTimeout(function(){
        botReply('<strong>' + title + '</strong><br>' + body, null, 300);
      }, delay + idx * 900);
    });
    var totalDelay = delay + data.sections.length * 900 + 600;
    setTimeout(function(){
      setMood('normal');
      var followUp = document.createElement('div');
      followUp.className = 'cb-chips';
      var triviaBtn = document.createElement('button');
      triviaBtn.className = 'cb-chip';
      triviaBtn.textContent = '🎮 Test your ' + data.label + ' knowledge!';
      triviaBtn.addEventListener('click', function(){
        followUp.querySelectorAll('.cb-chip').forEach(function(x){ x.style.pointerEvents='none'; x.style.opacity='.4'; });
        appendMsg('🎮 Test your ' + data.label + ' knowledge!', 'user');
        startTrivia(sport);
      });
      var moreBtn = document.createElement('button');
      moreBtn.className = 'cb-chip';
      moreBtn.textContent = '📖 Other sports rules';
      moreBtn.addEventListener('click', function(){
        followUp.querySelectorAll('.cb-chip').forEach(function(x){ x.style.pointerEvents='none'; x.style.opacity='.4'; });
        appendMsg('📖 Other sports rules', 'user');
        showRulesMenu();
      });
      followUp.appendChild(triviaBtn);
      followUp.appendChild(moreBtn);
      var closingLine = gl()==='tl'
        ? 'Ayos ba? 😏 Subukan mo ang trivia para ma-test ang iyong kaalaman!'
        : 'Got it all? 😏 Try the trivia to test what you learned!';
      botReply(closingLine, followUp, 400);
    }, totalDelay);
  }

  function handleUserMessage(text){
    text=(text||'').trim(); if(!text) return;
    lastInteraction=Date.now(); resetIdleTimer();
    appendMsg(text,'user');
    if(input){ input.value=''; input.style.height='38px'; }
    switch(detectIntent(text)){
      case 'greet':     setMood('happy',4000); botReply(rand(GREETINGS[lang||'en']),makeChips(gl()==='tl'?['Standings','Latest match','📖 Rules','🎮 Trivia','Help']:['Standings','Latest match','📖 Rules','🎮 Trivia','Help'])); break;
      case 'thanks':    setMood('happy',3000); botReply(gl()==='tl'?'Wagas! Anytime! 😎⚡':'Anytime, boss! 😎⚡'); break;
      case 'help':      botReply(HELP_TEXT[gl()]); break;
      case 'player':    handlePlayer(text); break;
      case 'team':      handleTeam(text);   break;
      case 'standings': handleStandings();  break;
      case 'latest':    handleLatest(null); break;
      case 'sport_bb':  handleLatest('basketball');   break;
      case 'sport_vb':  handleLatest('volleyball');   break;
      case 'sport_bd':  handleLatest('badminton');    break;
      case 'sport_tt':  handleLatest('table_tennis'); break;
      case 'sport_dr':  handleLatest('darts');        break;
      case 'trivia':    showTriviaMenu(); break;
      case 'trivia_bb': startTrivia('basketball');   break;
      case 'trivia_vb': startTrivia('volleyball');   break;
      case 'trivia_bd': startTrivia('badminton');    break;
      case 'trivia_tt': startTrivia('tabletennis');  break;
      case 'trivia_dr': startTrivia('darts');        break;
      case 'rules':     showRulesMenu(); break;
      case 'rules_bb':  showRules('basketball');   break;
      case 'rules_vb':  showRules('volleyball');   break;
      case 'rules_bd':  showRules('badminton');    break;
      case 'rules_tt':  showRules('tabletennis');  break;
      case 'rules_dr':  showRules('darts');        break;
      default: botReply(rand(FALLBACKS[gl()]),makeChips(gl()==='tl'
        ?['Help','Standings','📖 Rules','🎮 Trivia']
        :['Help','Standings','📖 Rules','🎮 Trivia']));
    }
  }

  /* ── INIT ─────────────────────────────────── */
  function init(){
    trigger      = document.getElementById('cb-trigger');
    face         = document.getElementById('cb-trigger-face');
    chatWindow   = document.getElementById('cb-window');
    closeBtn     = document.getElementById('cb-close');
    langScreen   = document.getElementById('cb-lang-screen');
    chatScreen   = document.getElementById('cb-chat-screen');
    messages     = document.getElementById('cb-messages');
    input        = document.getElementById('cb-input');
    sendBtn      = document.getElementById('cb-send');
    resetLangBtn = document.getElementById('cb-reset-lang');
    badge        = document.getElementById('cb-badge');
    avatarFace   = document.getElementById('cb-avatar-face');
    headerEl     = document.getElementById('cb-header');
    moodBar      = document.getElementById('cb-mood-bar');
    toastEl      = document.getElementById('cb-toast');

    if(!trigger){ console.warn('SyncBot: #cb-trigger not found.'); return; }

    trigger.addEventListener('click', handleTriggerClick);
    closeBtn.addEventListener('click', closeWindow);
    document.getElementById('cb-lang-en').addEventListener('click',function(){ selectLang('en'); });
    document.getElementById('cb-lang-tl').addEventListener('click',function(){ selectLang('tl'); });
    resetLangBtn.addEventListener('click',function(){
      lang=null; localStorage.removeItem('cb_lang');
      if(messages) messages.innerHTML='';
      showLangScreen(); setMood('normal');
    });
    sendBtn.addEventListener('click',function(){ handleUserMessage(input.value); });
    input.addEventListener('keydown',function(e){
      if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); handleUserMessage(input.value); }
    });
    input.addEventListener('input',function(){
      this.style.height='38px';
      this.style.height=Math.min(this.scrollHeight,100)+'px';
    });
    /* Outside-click closes window — but NEVER fires when clicking trigger
       (trigger has e.stopPropagation) */
    document.addEventListener('click',function(e){
      if(isOpen&&chatWindow&&!chatWindow.contains(e.target)&&trigger&&!trigger.contains(e.target)) closeWindow();
    });

    /* First-visit nudge */
    if(!localStorage.getItem('cb_opened')){
      setTimeout(function(){
        if(!isOpen){
          badge.textContent='1'; badge.classList.add('visible');
          showToast(gl()==='tl'?'👋 Hoy! Kausapin mo ako!':'👋 Hey! Chat with me!','idle',5000);
        }
      },4000);
    }

    setMood('normal');
    if(face){ face.textContent=IDLE_FACES[0]; face.style.transform=''; }
    scheduleFaceRotation();
    resetIdleTimer();
  }

  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',init);
  } else {
    init();
  }

})();


/* ================================================================
   ABOUT BOT — self-contained IIFE, zero interference with chatbot
================================================================ */
(function(){
  'use strict';

  var AB_CREW=[
    {name:'Joanner Relleve',      role:'Team Lead / System Architect',emoji:'J'},
    {name:'Kurt Oliver Pagatpat', role:'Backend (PHP/MySQL)',          emoji:'K'},
    {name:'Ludwig Agawin',        role:'Real-time Sync (SSOT)',        emoji:'L'},
    {name:'Realyn Mitra',         role:'UI/UX Design',                 emoji:'R'},
    {name:'Joanna Oademis',       role:'Frontend Dev',                 emoji:'J'},
    {name:'Aliah Lim',            role:'QA & Testing',                 emoji:'A'},
    {name:'Francis Genorilla',    role:'Data & Analytics',             emoji:'F'},
  ];
  var AB_CREW_REACTIONS=[
    'Solid to sila ha \u{1F60E}','Teamwork malala \u{1F4AF}',
    'Dito nagkaalaman kung sino puyat \u{1F62D}','Respect talaga \u{1F64C}',
    'Grabe yung dedication \u{1F620}','10 cups of coffee para dito \u{1F602}',
    'Silent hero ng system na ito \u{1F3C5}',
  ];
  var AB_CONTRIBUTIONS=[
    {icon:'\u{1F3D7}',label:'System Design',        key:'design'},
    {icon:'\u26A1',   label:'Real-time Sync (SSOT)', key:'sync'},
    {icon:'\u{1F3A8}',label:'UI/UX',                key:'uiux'},
    {icon:'\u{1F418}',label:'Backend (PHP/MySQL)',   key:'backend'},
    {icon:'\u{1F9EA}',label:'Testing & QA',          key:'qa'},
    {icon:'\u{1F4CA}',label:'Analytics',             key:'analytics'},
  ];
  var AB_CONTRIB_COMMENTS={
    design:  ['Ito yung pinaka-pinagisipan \u{1F620}','Dito nagsimula lahat \u{1F605}','Architecture decisions = endless debates \u{1F480}'],
    sync:    ['Ito pinaka-critical \u{1F620} Pag nag-break to... GG \u{1F62C}','WebSocket magic \u{1F52E}','Real-time = real stress \u{1F62D}'],
    uiux:    ['Dito nagkanda-ubos ang braincells \u{1F62D}','Revision number 47 yata \u{1F605}','Design is never done \u{1F612}'],
    backend: ['PHP warriors \u{1F4AA} Hindi sila nagquit','MySQL queries na gumawa ng sakit ng ulo \u{1F620}','Pero gumagana naman \u{1F60E}'],
    qa:      ['Sila ang naghahanap ng bugs... at nakakahanap \u{1F62C}','Break everything to fix everything \u{1F602}','Silent heroes \u{1F3C5}'],
    analytics:["Data do not lie... pero minsan mabagal \u{1F605}",'Charts, graphs, at numbers \u{1F4CA}','Tanungin ito kung may problema \u{1F60E}'],
  };
  var AB_STORIES=[
    {title:'\u{1F3C0} Basketball: Born from Boredom',lines:['Alam mo ba... yung basketball, naimbento lang dahil bored sila? \u{1F62D}','Si Dr. James Naismith, isang Canadian, ginawa niya ito noong 1891.','Yung original na basket? Literal na peach basket. Hindi nagbubukas sa ilalim. \u{1F602}','Ibig sabihin... pagkatapos ng bawat score, may kumuha ng bola manually. \u{1F62D}','Tapos ayun... naging global sport. Grabe yung glow up \u{1F633}\u26A1']},
    {title:'\u{1F3D0} Volleyball: Chill Sport na Hindi Pala Chill',lines:['Iniimbento ni William G. Morgan noong 1895 para sa mga mas matanda. \u{1F605}','Sabi niya, basketball daw ay "too intense".','Kaya gumawa siya ng sport na may net at hindi pwedeng hawakan yung bola.','Tapos ngayon? Pro players nag-eetrain ng 8 hours a day. \u{1F620}','Chill sport daw. Chill. \u{1F612}']},
    {title:'\u26A1 The Dream Team: 1992',lines:['Noong 1992 Barcelona Olympics, NBA players pinahintulutan na lumaro.','Nag-assemble yung Dream Team: Jordan, Magic, Bird, Barkley...','Average na panalo nila: 43.8 points. AVERAGE. \u{1F633}','Yung first game? 116-48. \u{1F480}','Yung larong ito ang nagpalit ng basketball globally forever. \u{1F30D}\u26A1']},
    {title:'\u{1F3D3} Table Tennis: Pajama to Olympics',lines:['Table tennis nagsimula bilang after-dinner game ng mga British aristocrats noong 1880s.','Ginamit nila yung mga libro bilang net. Mga cork bilang bola. \u{1F602}','Tapos naging Olympics sport siya noong 1988.','Ang China? Nanalo ng 28 out of 32 gold medals. 28. \u{1F620}','From pajama game hanggang Olympic domination. Respek. \u{1F3C5}']},
    {title:'\u{1F3AF} Darts: Pub Game Goes Pro',lines:['Darts! Ang sport na paborito ng lahat ng may beer sa kamay.','Nagsimula sa medieval England, soldiers nagtatapon ng arrows sa wine barrels.','Noong 1908 isang court case ang nagpasya na darts is a game of skill.','Kung natalo? Illegal na sana ang darts sa UK. \u{1F631}','Ngayon may pro players na kumikita ng millions. From pub to paychecks. \u{1F4B0}\u{1F60E}']},
  ];
  var AB_BOT_REACTIONS=['ANU BA \u{1F62D}','WAIT LANG, may plot twist pa \u{1F440}','Sige na nga... next na \u{1F612}','Ay grabe ka \u{1F62D} tuloy mo lang'];

  var abIsOpen=false, abCurrentTab='about', abStoryIdx=0, abQueue=[], abProcessing=false;
  var abOverlay, abChat, abAvatarFace;

  function abRand(a){ return a[Math.floor(Math.random()*a.length)]; }
  function abMd(t){ return (t||'').replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/\*(.+?)\*/g,'<em>$1</em>'); }
  function abScroll(){ if(abChat) abChat.scrollTop=abChat.scrollHeight; }

  function abEnqueue(fn,delay){ abQueue.push({fn:fn,delay:delay||0}); if(!abProcessing) abDrain(); }
  function abDrain(){ if(!abQueue.length){ abProcessing=false; return; } abProcessing=true; var item=abQueue.shift(); setTimeout(function(){ item.fn(); abDrain(); },item.delay); }

  function abBotMsg(html,ec){
    var w=document.createElement('div'); w.className='ab-msg ab-msg-bot';
    var b=document.createElement('div'); b.className='ab-bubble'+(ec?' '+ec:'');
    b.innerHTML=abMd(html); w.appendChild(b); abChat.appendChild(w); abScroll(); return w;
  }
  function abUserMsg(text){
    var w=document.createElement('div'); w.className='ab-msg ab-msg-user';
    var b=document.createElement('div'); b.className='ab-bubble';
    b.textContent=text; w.appendChild(b); abChat.appendChild(w); abScroll();
  }
  function abReaction(e){ var r=document.createElement('div'); r.className='ab-reaction ab-msg'; r.textContent=e; abChat.appendChild(r); abScroll(); }

  function abQ(html,delay,withTyping,ec){
    delay=delay||300;
    var td=withTyping?(500+Math.floor(Math.random()*400)):0;
    abEnqueue(function(){
      if(withTyping){ var d=document.createElement('div'); d.className='ab-typing'; d.id='ab-td'; d.innerHTML='<span></span><span></span><span></span>'; abChat.appendChild(d); abScroll(); }
    },delay);
    if(withTyping){ abEnqueue(function(){ var o=document.getElementById('ab-td'); if(o)o.remove(); abBotMsg(html,ec); },td); }
    else { abEnqueue(function(){ abBotMsg(html,ec); },0); }
  }
  function abQA(fn,delay){ abEnqueue(fn,delay||350); }

  function abAddChoices(items,onPick){
    var w=document.createElement('div'); w.className='ab-choices ab-msg';
    items.forEach(function(item){
      var b=document.createElement('button'); b.className='ab-choice'; b.textContent=item.label;
      b.addEventListener('click',function(){ w.querySelectorAll('.ab-choice').forEach(function(x){ x.classList.add('ab-used'); }); abUserMsg(item.label); onPick(item); });
      w.appendChild(b);
    });
    abChat.appendChild(w); abScroll();
  }
  function abAddCrewGrid(){
    var g=document.createElement('div'); g.className='ab-crew-grid ab-msg';
    AB_CREW.forEach(function(m,i){
      var c=document.createElement('div'); c.className='ab-crew-card'; c.style.animationDelay=(i*55)+'ms';
      c.innerHTML='<div class="ab-crew-avatar">'+m.emoji+'</div><div><div class="ab-crew-name">'+m.name+'</div><div class="ab-crew-role">'+m.role+'</div></div>';
      c.addEventListener('click',function(){ abUserMsg(m.name); abQ(abRand(AB_CREW_REACTIONS),200,true); });
      g.appendChild(c);
    });
    abChat.appendChild(g); abScroll();
  }
  function abAddContribs(){
    var l=document.createElement('div'); l.className='ab-contrib-list ab-msg';
    AB_CONTRIBUTIONS.forEach(function(item){
      var row=document.createElement('div'); row.className='ab-contrib-item';
      row.innerHTML='<span class="ab-contrib-icon">'+item.icon+'</span><span class="ab-contrib-label">'+item.label+'</span><span class="ab-contrib-arrow">\u2192</span>';
      row.addEventListener('click',function(){ abHandleContrib(item); });
      l.appendChild(row);
    });
    abChat.appendChild(l); abScroll();
  }
  function abClear(){ if(abChat) abChat.innerHTML=''; abQueue=[]; abProcessing=false; }

  function abActivateTab(key){
    abCurrentTab=key;
    document.querySelectorAll('.ab-tab').forEach(function(t){ t.classList.toggle('ab-tab-active',t.dataset.tab===key); });
    abClear();
    if(key==='about') abStartAbout();
    else if(key==='crew') abStartCrew();
    else if(key==='contrib') abStartContrib();
    else if(key==='story') abStartStory();
  }

  function abStartAbout(){
    if(abAvatarFace) abAvatarFace.textContent='\u{1F60F}';
    abQ('HOY! Curious ka about SportsSync? \u{1F60F}',0,true);
    abQ('Sige, kwento ko sa iyo... \u{1F447}',300,true);
    abQ('So, ano ba talaga ang <strong>SportsSync</strong>? \u{1F914}',400,true);
    abQA(function(){
      abAddChoices([{label:'\u{1F914} Ano siya exactly?',id:'what'},{label:'\u26A1 Ano ang kaya niya?',id:'can'},{label:'\u{1F465} Para kanino?',id:'who'},{label:'\u{1F527} Paano gumagana?',id:'how'}],abHandleAbout);
    },500);
  }
  function abHandleAbout(item){
    if(item.id==='what'){
      abQ('SportsSync ay isang <strong>real-time sports management system</strong>. \u{1F3C6}',200,true);
      abQ('Lahat ng scores, players, at results? <strong>Dito lahat.</strong> \u26A1',400,true);
      abQ('Real-time siya. Walang F5. Automatic mag-update. \u{1F60E}',300,true);
    } else if(item.id==='can'){
      abQ('Kaya niya ang marami! \u{1F620}',200,true);
      abQ('\u{1F3C0} Live scoring — basketball, volleyball, badminton, table tennis, darts',300,true);
      abQ('\u{1F4CA} Analytics — player stats, match history, performance trends',300,true);
      abQ('\u{1F4CB} Match reports — auto-generated pagkatapos ng laro',300,true);
    } else if(item.id==='who'){
      abQ('\u{1F511} <strong>Admins</strong> — nagco-control ng lahat.',300,true);
      abQ('\u{1F4FA} <strong>Viewers</strong> — nakaka-watch ng live scores.',300,true);
      abQ('\u{1F3C5} <strong>Scorekeepers</strong> — naglalagay ng scores sa live game.',300,true);
    } else {
      abQ('Step 1: Admin nag-eenter ng score sa live UI. \u270D',300,true);
      abQ('Step 2: System mag-save agad — <strong>Single Source of Truth (SSOT)</strong>. \u{1F5C4}',300,true);
      abQ('Step 3: Lahat ng viewers? <strong>Automatic nang nag-update.</strong> \u{1F504}',300,true);
    }
    abQA(function(){
      abAddChoices([{label:'\u{1F465} Meet the Crew',id:'crew'},{label:'\u{1F527} Contributions',id:'contrib'},{label:'\u{1F4D6} Tell me a story',id:'story'},{label:'\u{1F501} Ask something else',id:'more'}],
        function(c){ if(c.id==='more'){ abClear(); abStartAbout(); } else abActivateTab(c.id); });
    },500);
  }
  function abStartCrew(){
    if(abAvatarFace) abAvatarFace.textContent='\u{1F525}';
    abQ('\u{1F525} <strong>Behind this system... Built by:</strong>',0,true);
    abQA(function(){ abAddCrewGrid(); abReaction('\u{1F4AA}'); },400);
    abQ('I-click ang pangalan para makita ang reaction! \u{1F60F}',500,true);
    abQ(abRand(AB_CREW_REACTIONS),400,true);
    abQA(function(){
      abAddChoices([{label:'\u{1F527} Contributions',id:'contrib'},{label:'\u{1F4D6} Story',id:'story'},{label:'\u2190 About',id:'about'}],
        function(c){ abActivateTab(c.id); });
    },500);
  }
  function abStartContrib(){
    if(abAvatarFace) abAvatarFace.textContent='\u{1F6E0}';
    abQ('Eto ang mga major parts ng SportsSync! \u{1F6E0}',0,true);
    abQ('I-click ang bawat item para malaman ang <em>tea</em> nila \u{1F60F}',300,true);
    abQA(function(){ abAddContribs(); },400);
  }
  function abHandleContrib(item){
    var comments=AB_CONTRIB_COMMENTS[item.key]||['Grabe to \u{1F620}'];
    abQ(item.icon+' <strong>'+item.label+'</strong>',200,true,'ab-bubble-highlight');
    abQ(abRand(comments),300,true);
    abQA(function(){
      abAddChoices([{label:'\u{1F50D} Isa pa',id:'more'},{label:'\u{1F465} Crew',id:'crew'},{label:'\u{1F4D6} Story',id:'story'}],
        function(c){ if(c.id==='more'){ abQ('Pili ulit! \u{1F60F}',200,true); abQA(function(){ abAddContribs(); },400); } else abActivateTab(c.id==='crew'?'crew':'story'); });
    },400);
  }
  function abStartStory(){
    if(abAvatarFace) abAvatarFace.textContent='\u{1F4D6}';
    abStoryIdx=Math.floor(Math.random()*AB_STORIES.length);
    abQ('Story time! \u{1F4D6} Makinig ka muna! \u{1F620}',0,true);
    abQ(abRand(AB_BOT_REACTIONS),300,true);
    abQA(function(){ abPlayStory(abStoryIdx); },500);
  }
  function abPlayStory(idx){
    var story=AB_STORIES[idx];
    abQA(function(){
      var card=document.createElement('div'); card.className='ab-story-card ab-msg';
      var titleEl=document.createElement('div'); titleEl.className='ab-story-title'; titleEl.textContent=story.title;
      var bodyEl=document.createElement('div'); bodyEl.className='ab-story-body';
      card.appendChild(titleEl); card.appendChild(bodyEl);
      abChat.appendChild(card); abScroll();
      abDripLines(story.lines,bodyEl);
    },200);
  }
  function abDripLines(lines,container){
    var i=0;
    function next(){
      if(i>=lines.length){
        abQA(function(){
          abReaction('\u{1F633}');
          abAddChoices([{label:'\u27A1 Next story',id:'next'},{label:'\u{1F3B2} Random',id:'random'},{label:'\u2190 About',id:'about'}],
            function(c){ abClear(); if(c.id==='about'){ abActivateTab('about'); return; } abStoryIdx=(c.id==='next')?(abStoryIdx+1)%AB_STORIES.length:Math.floor(Math.random()*AB_STORIES.length); abStartStory(); });
        },600);
        return;
      }
      var line=lines[i]; i++;
      abEnqueue(function(){
        var dot=document.createElement('span'); dot.textContent=' \u23F3'; container.appendChild(dot); abScroll();
        setTimeout(function(){
          dot.remove();
          var p=document.createElement('p'); p.style.cssText='margin:4px 0;line-height:1.6;';
          p.innerHTML=abMd(line); container.appendChild(p); abScroll(); next();
        },600+Math.min(line.length*11,1100));
      },300);
    }
    next();
  }

  function abOpen(){ if(abIsOpen)return; abIsOpen=true; abOverlay.classList.add('ab-visible'); if(abChat.children.length===0) abActivateTab('about'); }
  function abClose(){ if(!abIsOpen)return; abIsOpen=false; abOverlay.classList.remove('ab-visible'); }

  function abInit(){
    abOverlay   = document.getElementById('ab-overlay');
    abChat      = document.getElementById('ab-chat');
    abAvatarFace= document.getElementById('ab-avatar-face-modal');
    if(!abOverlay) return;
    var openBtn=document.getElementById('ab-open-btn');
    if(openBtn) openBtn.addEventListener('click',abOpen);
    var closeBtn=document.getElementById('ab-close-modal');
    if(closeBtn) closeBtn.addEventListener('click',abClose);
    abOverlay.addEventListener('click',function(e){ if(e.target===abOverlay) abClose(); });
    document.querySelectorAll('.ab-tab').forEach(function(tab){ tab.addEventListener('click',function(){ abActivateTab(tab.dataset.tab); }); });
    var bs=document.getElementById('ab-footer-story'), bc=document.getElementById('ab-footer-crew'), bk=document.getElementById('ab-footer-contrib');
    if(bs) bs.addEventListener('click',function(){ abActivateTab('story'); });
    if(bc) bc.addEventListener('click',function(){ abActivateTab('crew'); });
    if(bk) bk.addEventListener('click',function(){ abActivateTab('contrib'); });
    document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&abIsOpen) abClose(); });
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',abInit);
  else abInit();

})();