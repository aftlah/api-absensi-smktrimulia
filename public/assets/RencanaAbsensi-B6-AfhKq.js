import{j as e}from"./query-vendor-CL32O2jR.js";import{r as l}from"./router-vendor-CjMceo_z.js";import{x as j,y as g,L as y,E as w,N as R,R as A,z as N}from"./admin-pages-BRzz_oB2.js";import"./ui-vendor-DAzBgmXi.js";import"./react-vendor-BZoW56qi.js";import"./utils-vendor-BmMXCZbk.js";const k=()=>{const{data:m,loading:c,error:n,refreshData:o}=j(),[d,a]=l.useState(!1),[f,r]=l.useState(null),{formData:u,handleChange:x,handleSubmit:h,resetForm:b}=g(o),i=(t,s)=>{r({type:t,message:s}),setTimeout(()=>r(null),3e3)},p=async t=>{const s=await h(t);s.success?(i("success",s.message),a(!1),b()):i("error",s.message)};return c?e.jsx(y,{text:"Memuat data rencana absensi..."}):n?e.jsx(w,{message:n}):e.jsxs("div",{className:"p-6 relative ",children:[e.jsx(R,{notification:f}),e.jsxs("div",{className:"flex items-center justify-between mb-8",children:[e.jsx("h1",{className:"text-3xl font-bold text-gray-800",children:"Rencana Absensi Siswa"}),e.jsx("button",{onClick:()=>a(!0),className:"px-5 py-2.5 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 shadow-md hover:shadow-lg transition-all",children:"+ Tambah Rencana"})]}),e.jsx(A,{data:m,onUpdated:o}),e.jsx(N,{show:d,onClose:()=>a(!1),formData:u,onChange:x,onSubmit:p}),e.jsx("style",{children:`
        @keyframes slideIn {
          from { transform: translateY(-20px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }
        .animate-slideIn { animation: slideIn 0.4s ease forwards; }

        @keyframes fadeIn {
          from { opacity: 0; transform: scale(0.95); }
          to { opacity: 1; transform: scale(1); }
        }
        .animate-fadeIn { animation: fadeIn 0.3s ease-out; }
      `})]})};export{k as default};
