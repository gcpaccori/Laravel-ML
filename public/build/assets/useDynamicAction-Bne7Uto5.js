function a(c={}){return{handleDynamicAction:({action:i,id:o,nameFuncion:n})=>{typeof c[n]=="function"?c[n](o):console.warn(`Función no encontrada: ${n}`)}}}export{a as u};
