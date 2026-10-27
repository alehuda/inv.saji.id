// Menjalankan seed() mockup (mode LIVE) tanpa browser, lalu menulis daftar item ke berkas JSON.
const fs=require('fs');
const html=fs.readFileSync(process.argv[2],'utf8');
const a=html.indexOf('<script>',html.indexOf('</aside>'));
let js=html.slice(html.lastIndexOf('<script>')+8,html.lastIndexOf('</script>'));
const tail='seed();if(LIVE){sbSet(false);lvBoot();}else{renderNotes();show("home");sbSet(window.innerWidth>=900);syStart();}';
if(!js.includes(tail))throw new Error('tail not found');
js=js.replace(tail,'seed();return items;');
const stub=new Proxy(function(){},{get:(t,k)=>k===Symbol.toPrimitive?()=> '':(k==='length'?0:stub),apply:()=>stub,construct:()=>stub,set:()=>true});
const win={SAJI_LIVE:{start:'home',room:'x',api:'/'},innerWidth:1200,addEventListener(){},location:{search:'',pathname:'/'},history:{replaceState(){}}};
const doc=stub;
global.MutationObserver=function(){return stub};global.ResizeObserver=global.MutationObserver;global.IntersectionObserver=global.MutationObserver;
const fn=new Function('window','document','localStorage','history','location','navigator','fetch','setInterval','setTimeout','clearTimeout','return '+js.trim().replace(/^;?/,''));
const items=fn(win,doc,{getItem(){return null},setItem(){}},win.history,win.location,{},()=>Promise.resolve({json:()=>({})}),()=>0,()=>0,()=>0);
console.error('items',items.length);
fs.writeFileSync(process.argv[3],JSON.stringify(items));
