(function(root,factory){
  'use strict';
  root.MapDrawingGuide=factory();
  if(typeof module!=='undefined'&&module.exports)module.exports=root.MapDrawingGuide;
})(typeof window!=='undefined'?window:globalThis,function(){
  'use strict';
  function dotSvg(document,index,selected){
    const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');
    Object.entries({width:28,height:28,viewBox:'0 0 28 28','aria-hidden':'true'}).forEach(([key,value])=>svg.setAttribute(key,value));
    const outer=document.createElementNS('http://www.w3.org/2000/svg','circle');
    Object.entries({cx:14,cy:14,r:12,fill:'#20362C'}).forEach(([key,value])=>outer.setAttribute(key,value));svg.appendChild(outer);
    const circle=document.createElementNS('http://www.w3.org/2000/svg','circle');
    Object.entries({cx:14,cy:14,r:10,fill:selected?'#FACC15':index===0?'#236344':'#2864B4',stroke:'#FFFFFF','stroke-width':2}).forEach(([key,value])=>circle.setAttribute(key,value));svg.appendChild(circle);
    const label=document.createElementNS('http://www.w3.org/2000/svg','text');
    Object.entries({x:14,y:18,'text-anchor':'middle','font-family':'Arial, sans-serif','font-size':index>=99?9:12,'font-weight':700,fill:selected?'#20362C':'#FFFFFF'}).forEach(([key,value])=>label.setAttribute(key,value));
    label.textContent=String(index+1);svg.appendChild(label);return svg;
  }
  function dotTemplate(document,index,selected){const tpl=document.createElement('template');tpl.content.appendChild(dotSvg(document,index,selected));return tpl;}
  function create(maps,document,map){
    if(!maps.OverlayView||!maps.LatLng||!maps.Polyline)return null;
    const line=new maps.Polyline({path:[],clickable:false,strokeColor:'#2864B4',strokeOpacity:1,strokeWeight:4,zIndex:12});
    const dots=[];
    function addDot(point,index){
      const marker=new maps.OverlayView();
      const element=document.createElement('span');element.setAttribute('aria-hidden','true');
      element.style.cssText='position:absolute;transform:translate(-50%,-50%);pointer-events:none;width:28px;height:28px;line-height:0';
      element.appendChild(dotSvg(document,index,false));
      marker.position=point;
      marker.onAdd=()=>marker.getPanes().overlayLayer.appendChild(element);
      marker.draw=()=>{
        const projection=marker.getProjection();if(!projection)return;
        const p=projection.fromLatLngToDivPixel(new maps.LatLng(marker.position.lat,marker.position.lng));
        if(p){element.style.left=p.x+'px';element.style.top=p.y+'px';}
      };
      marker.onRemove=()=>element.remove();marker.setMap(map);return marker;
    }
    return {
      update:points=>{
        const path=points.map(point=>({lat:typeof point.lat==='function'?point.lat():point.lat,lng:typeof point.lng==='function'?point.lng():point.lng}));
        line.setPath(path);line.setMap(path.length>=2?map:null);
        while(dots.length>path.length)dots.pop().setMap(null);
        path.forEach((point,index)=>{
          if(!dots[index])dots[index]=addDot(point,index);
          else {dots[index].position=point;dots[index].draw();}
        });
      },
      remove:()=>{line.setMap(null);dots.forEach(dot=>dot.setMap(null));dots.length=0;}
    };
  }
  return {dotSvg,dotTemplate,create};
});