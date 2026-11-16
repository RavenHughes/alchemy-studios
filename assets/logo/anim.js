/* Alchemy Studios: the logo assembles itself, piece by piece, is brazed into place, then comes alive.
   AlchemyLogo(el, {base:'assets/logo/', onDone:fn, still:false}) */
(function(){
'use strict';
var W=1253,H=1254;
var P={hinge:[563,0,129,173],knob:[352,425,77,77],leg:[286,123,347,661],sphere:[519,344,217,217],square:[615,136,385,657],
  nodes:[306,430,639,328],cross:[209,176,892,646],inner:[320,174,617,584],ring:[41,3,1212,819],
  al0:[50,823,145,138],al1:[234,823,114,138],al2:[378,823,120,138],al3:[543,823,145,138],al4:[731,823,121,138],al5:[889,823,166,138],al6:[1076,823,133,138],
  st0:[49,984,233,49],st1:[319,984,48,71],st2:[403,984,61,71],st3:[503,984,66,71],st4:[610,984,63,71],st5:[712,984,31,71],st6:[782,984,68,71],st7:[889,984,47,71],st8:[971,987,235,46],
  dh:[162,1080,915,33],im:[258,1155,948,31]};
var ORDER=['ring','inner','cross','nodes','square','leg','hinge','knob','sphere','al0','al1','al2','al3','al4','al5','al6','st0','st1','st2','st3','st4','st5','st6','st7','st8','dh','im'];
var Z={ring:1,inner:2,cross:3,nodes:4,square:5,sphere:6,leg:7,knob:8,hinge:9};

function AlchemyLogo(root,o){
  o=o||{};var base=o.base||'assets/logo/',reduce=o.still||matchMedia('(prefers-reduced-motion: reduce)').matches;
  root.classList.add('alogo');root.innerHTML='';
  var stage=document.createElement('div');stage.className='alogo-stage';root.appendChild(stage);
  var el={},loaded=0,total=ORDER.length;
  ORDER.forEach(function(n){var p=P[n],i=new Image();i.alt='';i.decoding='async';i.draggable=false;i.className='alogo-p alogo-'+n;
    i.style.left=(p[0]/W*100)+'%';i.style.top=(p[1]/H*100)+'%';i.style.width=(p[2]/W*100)+'%';i.style.zIndex=Z[n]||10;i.style.opacity=0;
    i.onload=i.onerror=function(){if(++loaded===total)start()};i.src=base+n+'.webp';stage.appendChild(i);el[n]=i});
  /* the life that comes in at the end: a spark of light travelling the inner circle, and the glow of the sphere */
  var NS='http://www.w3.org/2000/svg',svg=document.createElementNS(NS,'svg');svg.setAttribute('viewBox','0 0 '+W+' '+H);svg.setAttribute('class','alogo-life');
  svg.innerHTML='<defs><radialGradient id="alg"><stop offset="0" stop-color="#fff6d8" stop-opacity=".95"/><stop offset=".35" stop-color="#f3c45e" stop-opacity=".55"/><stop offset="1" stop-color="#c49a45" stop-opacity="0"/></radialGradient></defs>'+
    '<circle class="alogo-draft" cx="622" cy="445" r="368"/><circle class="alogo-draft" cx="625" cy="446" r="300"/><line class="alogo-draft" x1="210" y1="452" x2="1040" y2="452"/><line class="alogo-draft" x1="627" y1="20" x2="627" y2="805"/>'+
    '<circle class="alogo-orbit" cx="625" cy="446" r="300"/><circle class="alogo-orbit o2" cx="622" cy="445" r="368"/>'+
    '<circle class="alogo-core" cx="627" cy="452" r="150" fill="url(#alg)"/>';
  stage.appendChild(svg);
  var cv=document.createElement('canvas');cv.className='alogo-fx';stage.appendChild(cv);var g=cv.getContext('2d'),sparks=[],flashes=[],raf=0;
  var sheen=document.createElement('div');sheen.className='alogo-sheen';stage.appendChild(sheen);
  sheen.style.webkitMaskImage=sheen.style.maskImage='url('+base+'../brand/alchemy-logo-2027-720.webp)';

  function size(){var r=stage.getBoundingClientRect(),d=Math.min(window.devicePixelRatio||1,2);cv.width=r.width*d;cv.height=r.height*d}
  function fx(){var t=performance.now();g.clearRect(0,0,cv.width,cv.height);var sx=cv.width/W,sy=cv.height/H;
    g.globalCompositeOperation='lighter';
    flashes=flashes.filter(function(f){var a=(t-f.t)/f.d;if(a>1)return false;var r=(40+120*a)*sx,al=(1-a);
      var gr=g.createRadialGradient(f.x*sx,f.y*sy,0,f.x*sx,f.y*sy,r);gr.addColorStop(0,'rgba(255,250,235,'+al+')');gr.addColorStop(.25,'rgba(255,190,90,'+al*.8+')');gr.addColorStop(1,'rgba(255,120,30,0)');
      g.fillStyle=gr;g.beginPath();g.arc(f.x*sx,f.y*sy,r,0,6.283);g.fill();return true});
    sparks=sparks.filter(function(s){var a=(t-s.t)/s.l;if(a>1)return false;s.vy+=.38;s.vx*=.985;s.x+=s.vx;s.y+=s.vy;
      var c=a<.25?'255,245,220':a<.6?'255,190,80':'230,110,30';g.strokeStyle='rgba('+c+','+(1-a)+')';g.lineWidth=Math.max(1,2.2*sx*(1-a));
      g.beginPath();g.moveTo(s.x*sx,s.y*sy);g.lineTo((s.x-s.vx*2.2)*sx,(s.y-s.vy*2.2)*sy);g.stroke();return true});
    g.globalCompositeOperation='source-over';
    if(sparks.length||flashes.length)raf=requestAnimationFrame(fx);else raf=0}
  function braze(x,y,n){if(reduce)return;var t=performance.now();flashes.push({x:x,y:y,t:t,d:700});
    for(var i=0;i<(n||34);i++){var a=Math.random()*6.283,v=4+Math.random()*13;sparks.push({x:x,y:y,vx:Math.cos(a)*v,vy:Math.sin(a)*v-5,t:t,l:420+Math.random()*700})}
    if(!raf)raf=requestAnimationFrame(fx)}

  function an(n,kf,opt){var e=el[n];kf[kf.length-1].opacity=kf[kf.length-1].opacity==null?1:kf[kf.length-1].opacity;
    var a=e.animate(kf,Object.assign({fill:'forwards',easing:'cubic-bezier(.2,.8,.25,1)'},opt));return a}
  function origin(n,x,y){var p=P[n];el[n].style.transformOrigin=((x-p[0])/p[2]*100)+'% '+((y-p[1])/p[3]*100)+'%'}
  function at(ms,f){timers.push(setTimeout(f,ms))}
  var timers=[];

  function start(){size();addEventListener('resize',size);
    if(reduce){ORDER.forEach(function(n){el[n].style.opacity=1});root.classList.add('alive');o.onDone&&o.onDone();return}
    root.classList.add('drafting');
    origin('ring',622,445);origin('inner',625,446);origin('leg',627,108);origin('square',627,108);origin('hinge',627,108);origin('knob',390,463);origin('sphere',627,452);
    /* 1 the outer ring is set down and turned home */
    at(250,function(){an('ring',[{opacity:0,transform:'rotate(-70deg) scale(.86)',filter:'brightness(.4)'},{opacity:1,transform:'rotate(4deg) scale(1.01)',offset:.82,filter:'brightness(.9)'},{transform:'rotate(0) scale(1)',filter:'brightness(1)'}],{duration:1300})});
    at(1400,function(){braze(622,77,18);braze(622,813,18)});
    /* 2 the inner circle, counter-turned */
    at(1250,function(){an('inner',[{opacity:0,transform:'rotate(60deg) scale(1.06)'},{opacity:1,transform:'rotate(0) scale(1)'}],{duration:1000})});
    /* 3 the crosshairs are drawn out from the centre */
    at(1900,function(){an('cross',[{opacity:1,clipPath:'inset(48% 50% 48% 50%)'},{opacity:1,clipPath:'inset(0 0 0 0)'}],{duration:800,easing:'cubic-bezier(.6,.05,.3,1)'})});
    /* 4 the rivets are struck in */
    at(2600,function(){an('nodes',[{opacity:0,filter:'brightness(3)'},{opacity:1,filter:'brightness(1)'}],{duration:500});braze(328,452,14);braze(922,452,14);braze(627,733,14)});
    /* 5 the set square slides in along its edge and locks */
    at(3000,function(){an('square',[{opacity:0,transform:'translate(26%,-8%) rotate(10deg)'},{opacity:1,transform:'translate(-1%,0) rotate(-.6deg)',offset:.85},{transform:'none'}],{duration:1050})});
    at(4000,function(){braze(775,452,26);braze(652,170,22)});
    /* 6 the compass leg swings in on its pivot */
    at(4100,function(){an('leg',[{opacity:0,transform:'rotate(-42deg)'},{opacity:1,transform:'rotate(2.5deg)',offset:.8},{transform:'rotate(0)'}],{duration:1100})});
    at(5150,function(){braze(600,165,30);braze(340,452,16)});
    /* 7 the hinge drops onto the pivot, turning */
    at(5200,function(){an('hinge',[{opacity:0,transform:'translateY(-60%) rotate(-160deg)'},{opacity:1,transform:'translateY(3%) rotate(6deg)',offset:.78},{transform:'none'}],{duration:800})});
    at(5950,function(){braze(627,108,48)});
    /* 8 the knob is screwed in */
    at(6000,function(){an('knob',[{opacity:0,transform:'scale(1.6) rotate(-540deg)'},{opacity:1,transform:'scale(1) rotate(0)'}],{duration:850,easing:'cubic-bezier(.3,.7,.3,1)'})});
    at(6800,function(){braze(390,463,22)});
    /* 9 the sphere: the transformation point is lit */
    at(6900,function(){an('sphere',[{opacity:0,transform:'scale(.2)',filter:'brightness(4)'},{opacity:1,transform:'scale(1.08)',filter:'brightness(2.2)',offset:.6},{transform:'scale(1)',filter:'brightness(1)'}],{duration:1100});braze(627,452,60)});
    /* 10 the name is stamped, letter by letter */
    ['al0','al1','al2','al3','al4','al5','al6'].forEach(function(n,i){at(8000+i*150,function(){an(n,[{opacity:0,transform:'translateY(-22%) scale(1.06)',filter:'brightness(2.4)'},{opacity:1,transform:'translateY(2%)',offset:.7,filter:'brightness(1.3)'},{transform:'none',filter:'brightness(1)'}],{duration:420});var p=P[n];braze(p[0]+p[2]/2,p[1]+p[3]-6,10)})});
    at(9150,function(){el.st0.style.transformOrigin='100% 50%';el.st8.style.transformOrigin='0 50%';
      an('st0',[{opacity:1,transform:'scaleX(0)'},{opacity:1,transform:'scaleX(1)'}],{duration:700});an('st8',[{opacity:1,transform:'scaleX(0)'},{opacity:1,transform:'scaleX(1)'}],{duration:700})});
    ['st1','st2','st3','st4','st5','st6','st7'].forEach(function(n,i){at(9200+i*80,function(){an(n,[{opacity:0,transform:'translateY(18%)'},{opacity:1,transform:'none'}],{duration:500})})});
    at(9900,function(){an('dh',[{opacity:1,clipPath:'inset(0 50% 0 50%)'},{opacity:1,clipPath:'inset(0 0 0 0)'}],{duration:900,easing:'ease-out'})});
    at(10500,function(){an('im',[{opacity:0},{opacity:1}],{duration:900,easing:'ease'})});
    /* 11 it comes alive */
    at(10300,function(){root.classList.remove('drafting');root.classList.add('alive')});
    at(11300,function(){ORDER.forEach(function(n){el[n].getAnimations().forEach(function(a){try{a.commitStyles()}catch(e){}a.cancel()})})});
    at(11400,function(){o.onDone&&o.onDone()});
  }
  return {stop:function(){timers.forEach(clearTimeout);ORDER.forEach(function(n){el[n].getAnimations().forEach(function(a){a.finish()});el[n].style.opacity=1});root.classList.remove('drafting');root.classList.add('alive')}};
}
window.AlchemyLogo=AlchemyLogo;
})();
