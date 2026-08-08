const http=require('http'),fs=require('fs'),path=require('path');
const path=require('path'),fs=require('fs');
// The plugin sits in a subfolder in the monorepo and at the root of the public
// repository; serve whichever this is.
const UP=path.join(__dirname,'..');
const ROOT=fs.existsSync(path.join(UP,'3d-product-carousel.php'))?UP:path.join(UP,'3d-product-carousel');
const MIME={'.html':'text/html','.css':'text/css','.js':'text/javascript','.png':'image/png','.jpg':'image/jpeg','.svg':'image/svg+xml','.mp4':'video/mp4'};
http.createServer((req,res)=>{
  let p=decodeURIComponent(req.url.split('?')[0]);
  if(p==='/')p='/v2_8.html';
  const f=path.join(ROOT,p);
  fs.readFile(f,(e,d)=>{
    if(e){res.writeHead(404);res.end('404');return;}
    res.writeHead(200,{'Content-Type':MIME[path.extname(f).toLowerCase()]||'application/octet-stream'});
    res.end(d);
  });
}).listen(8777,()=>console.log('listening on 8777'));
