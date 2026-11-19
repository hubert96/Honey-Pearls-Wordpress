(() => {
 'use strict';
 function mount(root) {
  if(root.dataset.ready) return; root.dataset.ready='1';
  const slides=[...root.querySelectorAll('.hp-fb-slide')]; if(!slides.length)return;
  const height=Number(root.dataset.height)||740; let index=0, timer;
  function size() {
   const slide=slides[index], stage=slide.querySelector('.hp-fb-stage'), frame=stage.querySelector('iframe');
   const available=Math.floor(slide.clientWidth); if(available<1)return;
   const width=Math.max(350,Math.min(500,available)), scale=Math.min(1,available/width);
   const frameHeight=Math.ceil(height/scale);
   stage.style.width=`${width*scale}px`;stage.style.height=`${height}px`;
   frame.style.width=`${width}px`;frame.style.height=`${frameHeight}px`;frame.style.transform=`scale(${scale})`;
   const src=new URL('https://www.facebook.com/plugins/post.php');src.search=new URLSearchParams({href:frame.dataset.post,show_text:'true',width:String(width),height:String(frameHeight)});
   if(frame.src!==src.href)frame.src=src.href;
  }
  function show(next){
   index=(next+slides.length)%slides.length;
   slides.forEach((slide,i)=>{slide.hidden=i!==index;slide.inert=i!==index;slide.setAttribute('aria-hidden',String(i!==index));});
   const count=root.querySelector('.hp-fb-count');if(count)count.textContent=`${index+1} / ${slides.length}`;
   size();
  }
  root.classList.add('is-ready');const nav=root.querySelector('.hp-fb-nav');if(nav)nav.hidden=false;
  root.querySelectorAll('[data-step]').forEach(button=>button.addEventListener('click',()=>show(index+Number(button.dataset.step))));
  // No autoplay: never interrupt reading or interaction inside Facebook's frame.
  new ResizeObserver(()=>{clearTimeout(timer);timer=setTimeout(size,150);}).observe(root);
  show(0);
 }
 const init=()=>document.querySelectorAll('.hp-fb').forEach(mount);
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
