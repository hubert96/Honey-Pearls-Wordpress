(() => {
 'use strict';
 function mount(root) {
  if(root.dataset.ready) return; root.dataset.ready='1';
  const slides=[...root.querySelectorAll('.hp-fb-slide')];
  const height=Number(root.dataset.height)||740; let timer;
  function size() {
   slides.forEach(slide=>{
    const stage=slide.querySelector('.hp-fb-stage'),frame=stage.querySelector('iframe');
    const available=Math.floor(slide.clientWidth); if(available<1)return;
    const width=Math.max(350,Math.min(750,available)),scale=available/width;
    const frameHeight=Math.ceil(height/scale);
    stage.style.width=`${available}px`;stage.style.height=`${height}px`;
    frame.style.width=`${width}px`;frame.style.height=`${frameHeight}px`;frame.style.transform=`scale(${scale})`;
    const src=new URL('https://www.facebook.com/plugins/post.php');
    src.search=new URLSearchParams({href:frame.dataset.post,show_text:'true',width:String(width),height:String(frameHeight)});
    if(frame.src!==src.href)frame.src=src.href;
   });
  }
  new ResizeObserver(()=>{clearTimeout(timer);timer=setTimeout(size,150);}).observe(root);
  size();
 }
 const init=()=>document.querySelectorAll('.hp-fb').forEach(mount);
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
