/* Alchemy Studios obsidian finish (preview). ?finish=obsidian turns it on for this browser, ?finish=classic turns it off. */
(function(){
  'use strict';var KEY='alchemy-finish',on=false;
  try{var q=new URLSearchParams(location.search).get('finish');if(q==='obsidian'||q==='classic')localStorage.setItem(KEY,q);on=(localStorage.getItem(KEY)||window.ALCHEMY_FINISH_DEFAULT||'classic')==='obsidian'}
  catch(_){on=/finish=obsidian/.test(location.search)}
  if(!on)return;
  var root=document.documentElement;root.classList.add('obsidian');var reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
  function fiber(){
    var c=document.createElement('canvas');c.id='obs-fiber';c.setAttribute('aria-hidden','true');document.body.insertBefore(c,document.body.firstChild);
    var g=c.getContext('2d'),DPR=Math.min(window.devicePixelRatio||1,1.5),W,H,last=0,P=null;
    function mote(any){var r=Math.random(),big=r>.9;return{x:Math.random()*W,y:any?Math.random()*H:H+10,r:big?1.6+Math.random()*1.4:.5+Math.random()*.9,v:.12+Math.random()*.45,w:.2+Math.random()*.6,p:Math.random()*6.3,a:big?.55+Math.random()*.35:.18+Math.random()*.4,c:Math.random()<.55?'127,211,255':Math.random()<.6?'139,108,255':'217,164,65'}}
    function size(){W=c.width=innerWidth*DPR;H=c.height=innerHeight*DPR}size();addEventListener('resize',size);
    function draw(now){
      if(!reduce)requestAnimationFrame(draw);if(now-last<33)return;last=now;
      var t=now/1000;g.clearRect(0,0,W,H);g.lineWidth=.7*DPR;
      for(var fam=0;fam<2;fam++)for(var k=0;k<24;k++){
        g.beginPath();var base=H*(.03+k*.042),amp=H*.05,ph=k*.42+(fam?Math.PI:0)+(reduce?0:t*(fam?.07:-.05)),f=(fam?2.2:1.7)*Math.PI/W;
        for(var x=0;x<=W;x+=8*DPR){var y=base+Math.sin(x*f+ph)*amp+Math.sin(x*f*3.1-ph*.7)*amp*.22;x?g.lineTo(x,y):g.moveTo(x,y)}
        g.strokeStyle=fam?'rgba(139,108,255,'+(.05+.025*Math.sin(k+t*.4))+')':'rgba(190,200,220,.035)';g.stroke()}
      /* particles: fine motes of light drifting up through the dark, with a few that glow */
      if(!P){P=[];for(var n=0;n<(innerWidth<700?55:120);n++)P.push(mote(true))}
      for(var m=0;m<P.length;m++){var q=P[m];if(!reduce){q.y-=q.v*DPR;q.x+=Math.sin(t*q.w+q.p)*.25*DPR}
        if(q.y<-10)P[m]=q=mote(false);var tw=.55+.45*Math.sin(t*q.w*3+q.p),a=q.a*tw;
        g.beginPath();g.arc(q.x,q.y,q.r*DPR,0,6.283);g.fillStyle='rgba('+q.c+','+a+')';
        if(q.r>1.4){g.shadowColor='rgba('+q.c+',.9)';g.shadowBlur=10*DPR}else g.shadowBlur=0;g.fill()}
      g.shadowBlur=0;
      var cx=W*.74,cy=H*.34,R=Math.min(W,H)*.36;g.strokeStyle='rgba(127,211,255,.06)';
      for(var j=0;j<3;j++){g.beginPath();for(var a=0;a<=6.3;a+=.012){var r=R*(.78+j*.08)+R*.08*Math.sin(a*18+(reduce?0:t*.12)+j),px=cx+Math.cos(a)*r,py=cy+Math.sin(a)*r;a?g.lineTo(px,py):g.moveTo(px,py)}g.stroke()}}
    requestAnimationFrame(draw)}
  function leds(){var s=document.querySelector('.sheet');if(!s)return;['led0','led1 lv','led2'].forEach(function(n){var i=document.createElement('i');i.className='led '+n;s.appendChild(i)})}
  function go(){fiber();leds()}
  document.readyState==='loading'?document.addEventListener('DOMContentLoaded',go):go();
})();
