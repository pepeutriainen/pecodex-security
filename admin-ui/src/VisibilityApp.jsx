import React, { useState, useEffect } from 'react';
import {
  Layers,
  Search,
  FileText,
  Image as ImageIcon,
  Globe,
  Settings,
  Lock,
  Unlock,
  Check,
  ChevronDown,
  Monitor,
  Code,
  Eye,
  Download,
  Share2,
  X,
  Plus,
  MoreHorizontal,
  Loader2,
  Folder,
  ExternalLink
} from 'lucide-react';

const IframePreview = ({ url, title }) => {
  const [isVisible, setIsVisible] = useState(false);
  const [isLoaded, setIsLoaded] = useState(false);
  const containerRef = React.useRef(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
          observer.disconnect();
        }
      },
      { rootMargin: '200px' }
    );
    if (containerRef.current) {
      observer.observe(containerRef.current);
    }
    return () => observer.disconnect();
  }, []);

  return (
    <div ref={containerRef} className="w-full h-full bg-slate-50 relative flex items-center justify-center">
      {!isLoaded && (
        <div className="absolute inset-0 flex items-center justify-center">
          <Loader2 className={`w-16 h-16 animate-spin ${isVisible ? 'text-[#b80048] opacity-50' : 'text-slate-300'}`} />
        </div>
      )}
      
      {isVisible && (
        <iframe 
          src={url} 
          onLoad={() => setIsLoaded(true)}
          className={`w-full h-full border-0 absolute inset-0 transition-opacity duration-500 ${isLoaded ? 'opacity-100' : 'opacity-0'}`} 
          loading="lazy"
          tabIndex="-1"
          title={title} 
        />
      )}
    </div>
  );
};

export default function App() {
  const [items, setItems] = useState([]);
  const [availableFolders, setAvailableFolders] = useState([]);
  const [subRoles, setSubRoles] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedItemId, setSelectedItemId] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isFolderDropdownOpen, setIsFolderDropdownOpen] = useState(false);
  const [isProtectionDropdownOpen, setIsProtectionDropdownOpen] = useState(false);
  const [activeCardOverlay, setActiveCardOverlay] = useState(null);
  const [tempRoleBasedState, setTempRoleBasedState] = useState({});
  const [filterMode, setFilterMode] = useState('all');

  // Fetch data on mount
  useEffect(() => {
    const fetchData = async () => {
      try {
        const { root, nonce } = window.pgmVisibilityApi || {};
        if (!root) {
          // Fallback data for local development if window.pgmVisibilityApi is missing
          setSubRoles([
            { id: 'sub_talous', name: 'Talousosasto', count: 0 },
            { id: 'sub_hr', name: 'HR & Henkilöstö', count: 0 },
            { id: 'sub_projektit', name: 'Projektitiimit', count: 0 },
            { id: 'sub_vip', name: 'VIP-Jäsenet', count: 0 }
          ]);
          setItems([
            { id: 'p_1', title: 'Taloushallinnon vuosikatsaus 2026', type: 'page', allowedRoles: ['sub_talous'], isProtected: true },
            { id: 'p_2', title: 'Henkilöstö- ja perehdytysopas', type: 'page', allowedRoles: ['sub_hr'], isProtected: true },
            { id: 'p_1', title: 'Taloushallinnon vuosikatsaus 2026', type: 'page', allowedRoles: ['sub_talous'], isProtected: true },
            { id: 'p_2', title: 'Henkilöstö- ja perehdytysopas', type: 'page', allowedRoles: ['sub_hr'], isProtected: true }
          ]);
          setIsLoading(false);
          return;
        }

        const headers = { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' };
        
        const [rolesRes, itemsRes] = await Promise.all([
          fetch(`${root}pgm/v1/roles`, { headers }),
          fetch(`${root}pgm/v1/items`, { headers })
        ]);

        if (rolesRes.ok && itemsRes.ok) {
          const rolesData = await rolesRes.json();
          const itemsData = await itemsRes.json();
          setSubRoles(rolesData);
          if (itemsData.items) {
            setItems(itemsData.items);
            setAvailableFolders(itemsData.folders || []);
          } else {
            setItems(itemsData);
          }
        }
      } catch (err) {
        console.error('Error fetching visibility data:', err);
      } finally {
        setIsLoading(false);
      }
    };
    fetchData();
  }, []);

  // Filter logic
  const allSearchedPages = items.filter(i => (i.title || '').toLowerCase().includes((searchQuery || '').toLowerCase()));
  
  const publicCount = allSearchedPages.filter(i => i.protectionType === 'none' || !i.protectionType).length;
  const protectedCount = allSearchedPages.filter(i => i.protectionType && i.protectionType !== 'none').length;

  const pages = allSearchedPages.filter(i => {
    const isProt = i.protectionType && i.protectionType !== 'none';
    if (filterMode === 'public') return !isProt;
    if (filterMode === 'protected') return isProt;
    return true;
  });

  const selectedItem = items.find(i => i.id === selectedItemId);

  // Body scroll lock when modal is open
  useEffect(() => {
    if (activeCardOverlay) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [activeCardOverlay]);

  // Toggle Linked Folder
  const toggleLinkedFolder = async (itemId, folderId, isChecked) => {
    const item = items.find(i => i.id === itemId);
    if (!item) return;

    const currentFolders = item.linkedFolders || [];
    let newFolders = [];
    if (isChecked) {
      newFolders = [...currentFolders, folderId];
    } else {
      newFolders = currentFolders.filter(id => id !== folderId);
    }

    setItems(prev => prev.map(i => {
      if (i.id === itemId) return { ...i, linkedFolders: newFolders };
      return i;
    }));

    try {
      const { root, nonce } = window.pgmVisibilityApi || {};
      if (root) {
        await fetch(`${root}pgm/v1/items/roles`, {
          method: 'POST',
          headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
          body: JSON.stringify({ itemId, roles: item.allowedRoles, linkedFolders: newFolders, protectionType: item.protectionType, noAccessMessage: item.noAccessMessage })
        });
      }
    } catch (err) {
      console.error('Error saving linked folders:', err);
    }
  };

  // Save Roles
  const saveItemRoles = async (itemId, roleId, isChecked) => {
    const item = items.find(i => i.id === itemId);
    if (!item) return;

    const newRoles = isChecked 
      ? [...item.allowedRoles, roleId]
      : item.allowedRoles.filter(r => r !== roleId);

    // Optimistic update
    setItems(prev => prev.map(i => {
      if (i.id === itemId) {
        const isProt = newRoles.length > 0 || i.protectionType === 'floauth' || i.protectionType === 'password' || i.protectionType === 'logged_in';
        return { ...i, allowedRoles: newRoles, isProtected: isProt };
      }
      return i;
    }));

    try {
      const { root, nonce } = window.pgmVisibilityApi || {};
      if (root) {
        await fetch(`${root}pgm/v1/items/roles`, {
          method: 'POST',
          headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
          body: JSON.stringify({ itemId, roles: newRoles, linkedFolder: item.linkedFolder || '', protectionType: item.protectionType, noAccessMessage: item.noAccessMessage })
        });
      }
    } catch (err) {
      console.error('Error saving roles:', err);
    }
  };

  const saveProtectionTypeAndRoles = async (itemId, newType, newRoles) => {
    const item = items.find(i => i.id === itemId);
    if (!item) return;

    setItems(prev => prev.map(i => {
      if (i.id === itemId) {
        const isProt = newType !== 'none';
        return { ...i, protectionType: newType, allowedRoles: newRoles, isProtected: isProt };
      }
      return i;
    }));

    try {
      const { root, nonce } = window.pgmVisibilityApi || {};
      if (root) {
        await fetch(`${root}pgm/v1/items/roles`, {
          method: 'POST',
          headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
          body: JSON.stringify({ itemId, roles: newRoles, linkedFolder: item.linkedFolder || '', protectionType: newType, noAccessMessage: item.noAccessMessage })
        });
      }
    } catch (err) {
      console.error('Error saving protection type and roles:', err);
    }
  };

  // Save Protection Type
  const saveProtectionType = async (itemId, newType) => {
    const item = items.find(i => i.id === itemId);
    if (!item) return;

    setItems(prev => prev.map(i => {
      if (i.id === itemId) {
        const isProt = newType !== 'none';
        return { ...i, protectionType: newType, isProtected: isProt };
      }
      return i;
    }));

    try {
      const { root, nonce } = window.pgmVisibilityApi || {};
      if (root) {
        await fetch(`${root}pgm/v1/items/roles`, {
          method: 'POST',
          headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
          body: JSON.stringify({ itemId, roles: item.allowedRoles, linkedFolder: item.linkedFolder || '', protectionType: newType, noAccessMessage: item.noAccessMessage })
        });
      }
    } catch (err) {
      console.error('Error saving protection type:', err);
    }
  };

  // Save No Access Message
  const saveNoAccessMessage = async (itemId, message) => {
    const item = items.find(i => i.id === itemId);
    if (!item) return;

    setItems(prev => prev.map(i => {
      if (i.id === itemId) return { ...i, noAccessMessage: message };
      return i;
    }));

    try {
      const { root, nonce } = window.pgmVisibilityApi || {};
      if (root) {
        await fetch(`${root}pgm/v1/items/roles`, {
          method: 'POST',
          headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
          body: JSON.stringify({ itemId, roles: item.allowedRoles, linkedFolder: item.linkedFolder || '', protectionType: item.protectionType, noAccessMessage: message })
        });
      }
    } catch (err) {
      console.error('Error saving no access message:', err);
    }
  };

  const getIcon = (type) => {
    if (type === 'page') return <Globe className="w-4 h-4 text-[#b80048]" />;
    if (type === 'img') return <ImageIcon className="w-4 h-4 text-[#b80048]" />;
    return <FileText className="w-4 h-4 text-red-500" />;
  };

  if (isLoading) {
    return <div className="p-8 text-center text-slate-500">Ladataan työskentelytilaa...</div>;
  }

  return (
    <div className="flex flex-col h-screen bg-white font-sans text-[13px] text-slate-800" style={{minHeight: "100vh", marginLeft: "-20px", marginTop: "-20px", marginRight: "-20px"}}>
      
      {/* TOP TOOLBAR */}
      <header className="h-14 border-b border-slate-200 bg-white flex items-center justify-between px-4 shrink-0 shadow-sm z-10">
        <div className="flex items-center space-x-6">
          <div className="font-bold text-slate-800 flex items-center space-x-2 text-lg px-2">
            <Monitor className="w-5 h-5 text-[#b80048]" />
            <span>Pecodex Security</span>
          </div>
          <div className="flex items-center text-slate-500 space-x-4">
            <span className="bg-slate-100 px-2 py-1 rounded text-xs border border-slate-200">Role & File Permissions</span>
          </div>
        </div>
      </header>

      {/* MAIN WORKSPACE */}
      <div className="flex flex-1 overflow-hidden">
        
        {/* LEFT SIDEBAR: Layers & Artboards */}
        <aside className="w-[280px] bg-slate-50 border-r border-slate-200 flex flex-col shrink-0">
          <div className="p-4 border-b border-slate-200 flex items-center justify-between">
            <div className="flex items-center font-medium text-slate-700">
              <Layers className="w-4 h-4 mr-2" />
              Tasot & Kohteet
            </div>
            <span className="text-xs text-slate-400 font-medium bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">{items.length} items</span>
          </div>

          <div className="px-3 pb-3">
            <div className="relative mb-3 mt-1">
              <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input 
                type="text" 
                placeholder="Etsi tasoa..." 
                value={searchQuery}
                onChange={e => setSearchQuery(e.target.value)}
                className="w-full !pl-9 pr-3 py-2 bg-white border border-slate-300 rounded-md text-[13px] focus:outline-none focus:border-[#b80048] focus:ring-1 focus:ring-[#b80048] transition shadow-inner"
              />
            </div>
            <div className="flex flex-col gap-2">
              <button 
                onClick={() => setFilterMode('all')}
                className={`w-full px-3 py-2 rounded-md text-[11px] transition-all border ${filterMode === 'all' ? 'border-slate-800 bg-white shadow-sm text-slate-800 font-bold' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700 font-medium'}`}
              >
                Kaikki ({allSearchedPages.length})
              </button>
              <div className="flex items-center gap-2">
                <button 
                  onClick={() => setFilterMode('public')}
                  className={`flex-1 px-2 py-2 rounded-md text-[11px] transition-all border ${filterMode === 'public' ? 'border-slate-800 bg-white shadow-sm text-slate-800 font-bold' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700 font-medium'}`}
                >
                  Julkiset ({publicCount})
                </button>
                <button 
                  onClick={() => setFilterMode('protected')}
                  className={`flex-1 px-2 py-2 rounded-md text-[11px] transition-all border ${filterMode === 'protected' ? 'border-pink-600 bg-[#b80048]/5 shadow-sm text-[#96003a] font-bold' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700 font-medium'}`}
                >
                  Suojatut ({protectedCount})
                </button>
              </div>
            </div>
          </div>

          <div className="flex-1 overflow-y-auto p-3 pt-0 space-y-6">
            
            {/* WP SIVUT */}
            <div>
              <div className="flex items-center justify-between mb-2 px-1">
                <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider">WP Sivut (Artboards)</span>
                <Globe className="w-3.5 h-3.5 text-slate-400" />
              </div>
              <div className="space-y-0.5">
                {pages.map(page => (
                  <button 
                    key={page.id}
                    onClick={() => {
                      setSelectedItemId(page.id);
                      const el = document.getElementById(`card-${page.id}`);
                      if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                      }
                    }}
                    className={`w-full flex items-center px-2 py-1.5 rounded cursor-pointer transition text-left ${selectedItemId === page.id ? 'bg-[#b80048]/10 text-[#96003a] font-medium' : 'hover:bg-slate-200 text-slate-700'}`}
                  >
                    {getIcon(page.type)}
                    <span className="ml-2 truncate flex-1">{page.title}</span>
                    {page.isProtected && <Lock className={`w-3 h-3 ${selectedItemId === page.id ? 'text-[#b80048]' : 'text-slate-400'}`} />}
                  </button>
                ))}
                  {pages.length === 0 && <div className="text-xs text-slate-400 px-2">Ei sivuja</div>}
                </div>
              </div>

            </div>
          </aside>

        {/* CENTER CANVAS */}
        <main className="flex-1 bg-slate-200 overflow-auto relative p-8 shadow-inner" style={{ backgroundImage: 'radial-gradient(#cbd5e1 1px, transparent 0)', backgroundSize: '20px 20px' }}>
          
          <div className="flex flex-wrap gap-12 items-start justify-center min-h-full">
            {pages.length === 0 ? (
              <div className="flex flex-col items-center justify-center h-full text-slate-400 mt-32">
                <div className="bg-white/50 p-6 rounded-full shadow-sm mb-4">
                  <Search className="w-10 h-10 text-slate-400 opacity-50" />
                </div>
                <h3 className="text-lg font-medium text-slate-500 mb-2">Ei tuloksia</h3>
                <p className="text-sm">Yritä muuttaa hakusanaa tai suodattimia löytääksesi etsimäsi sivu.</p>
              </div>
            ) : (
              pages.map((item, idx) => (
                <div 
                key={item.id}
                id={`card-${item.id}`}
                onClick={() => setSelectedItemId(item.id)}
                className={`relative flex flex-col bg-white rounded shadow-lg transition-all duration-300 ease-out cursor-pointer ${selectedItemId === item.id ? 'ring-2 ring-[#b80048] transform scale-[1.03] shadow-xl -translate-y-1' : 'hover:scale-[1.04] hover:-translate-y-1.5 hover:shadow-2xl'}`}
                style={{ width: '320px', height: '420px' }}
              >
                {/* Canvas Frame Label */}
                <div className="absolute -top-6 left-0 text-slate-500 text-[12px] font-medium flex items-center">
                  <span className="text-slate-400 mr-1">#</span> {item.title}
                </div>

                {/* Card Header */}
                <div className="bg-[#b80048] text-white px-3 py-2 rounded-t flex items-center justify-between text-xs font-medium">
                  <div className="flex items-center font-bold tracking-wide">
                    {item.type === 'page' ? <Globe className="w-3.5 h-3.5 mr-1.5" /> : 
                     <FileText className="w-3.5 h-3.5 mr-1.5" />}
                    {item.type === 'page' ? 'Sivu' : 'Media'}
                  </div>
                  <div className="text-[#fdf2f6] opacity-90 truncate max-w-[200px] text-[10px]" title={item.url || ''}>
                    {item.url ? item.url.replace(/^https?:\/\/[^\/]+/, '') : ''}
                  </div>
                </div>

                {/* Card Body - Simulated Content */}
                <div className="flex-1 border-x border-b border-slate-200 rounded-b p-4 flex flex-col relative overflow-hidden bg-white">
                  {item.isProtected && (
                    <div className="absolute top-4 right-4 text-slate-400">
                      <Lock className="w-4 h-4" />
                    </div>
                  )}
                  
                  <div className="mb-4">
                    <div className="text-[10px] font-bold text-[#b80048] uppercase tracking-wider mb-1">
                      {item.type === 'page' ? 'SIVU / ARTIKKELI' : 'MEDIA / TIEDOSTO'}
                    </div>
                    <h3 className="text-[18px] font-bold text-slate-800 leading-tight pr-6">{item.title}</h3>
                  </div>

                  <div className="flex-1 relative group overflow-hidden bg-white">
                    <div className="absolute inset-0 w-[400%] h-[400%] origin-top-left scale-[0.25] pointer-events-none">
                       <IframePreview url={item.url} title={item.title} />
                    </div>
                     <div className="absolute inset-0 bg-transparent group-hover:bg-[#b80048]/5 transition-colors pointer-events-none"></div>
                  </div>
                  

                  {/* Badges & Actions */}
                  <div className="mt-4 flex items-center justify-between">
                    <div>
                      {item.protectionType === 'roles' ? (
                        <div className="bg-slate-100 text-slate-600 text-[10px] font-medium px-2 py-1 rounded inline-flex items-center border border-slate-200">
                          <Lock className="w-3 h-3 mr-1 text-[#b80048]" /> Suojattu {item.allowedRoles.length} roolilla
                        </div>
                      ) : item.protectionType === 'floauth' ? (
                        <div className="bg-slate-100 text-slate-600 text-[10px] font-medium px-2 py-1 rounded inline-flex items-center border border-slate-200">
                          <Lock className="w-3 h-3 mr-1 text-[#b80048]" /> Suojattu (FloMembers)
                        </div>
                      ) : item.protectionType === 'logged_in' ? (
                        <div className="bg-slate-100 text-slate-600 text-[10px] font-medium px-2 py-1 rounded inline-flex items-center border border-slate-200">
                          <Lock className="w-3 h-3 mr-1 text-[#b80048]" /> Suojattu (Kirjautuneet)
                        </div>
                      ) : item.protectionType === 'password' ? (
                        <div className="bg-slate-100 text-slate-600 text-[10px] font-medium px-2 py-1 rounded inline-flex items-center border border-slate-200">
                          <Lock className="w-3 h-3 mr-1 text-purple-600" /> Salasanasuojattu
                        </div>
                      ) : (
                        <div className="bg-slate-100 text-slate-500 text-[10px] font-medium px-2 py-1 rounded inline-flex items-center border border-slate-200">
                          <Unlock className="w-3 h-3 mr-1" /> Julkinen
                        </div>
                      )}
                    </div>
                    <div className="flex items-center gap-1.5">
                      <a
                        href={item.url || '#'}
                        target="_blank"
                        rel="noreferrer"
                        onClick={(e) => e.stopPropagation()}
                        className="flex items-center text-[11px] font-bold px-2 py-1.5 rounded transition-colors bg-slate-50 text-slate-500 hover:bg-slate-100 hover:text-slate-700 border border-slate-200"
                        title="Avaa sivu uuteen välilehteen"
                      >
                        <ExternalLink className="w-4 h-4" />
                      </a>
                      <button
                        onClick={(e) => { e.stopPropagation(); setActiveCardOverlay(activeCardOverlay === item.id ? null : item.id); }}
                        className={`flex items-center text-[11px] font-bold px-3 py-1.5 rounded transition-colors ${(item.linkedFolders && item.linkedFolders.length > 0) ? 'bg-[#b80048]/10 text-[#96003a] hover:bg-pink-200 border border-[#b80048]/20' : 'bg-slate-50 text-slate-500 hover:bg-slate-100 hover:text-slate-700 border border-slate-200'}`}
                        title="Hallitse liitettyjä mediakansioita"
                      >
                        <Folder className="w-3.5 h-3.5 mr-1.5" />
                        {(item.linkedFolders && item.linkedFolders.length > 0) ? `Liitetty (${item.linkedFolders.length})` : 'Liitä kansioita'}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            ))
            )}
          </div>


        </main>

        {/* RIGHT SIDEBAR: Inspector & Properties */}
        <aside className="w-[320px] bg-white border-l border-slate-200 flex flex-col shrink-0 z-20 shadow-[-4px_0_15px_-3px_rgba(0,0,0,0.05)]">
          <div className="p-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <div className="flex items-center font-semibold text-slate-700">
              <Settings className="w-4 h-4 mr-2 text-[#b80048]" />
              Inspector & Properties
            </div>
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">WP ACCESS</span>
          </div>

          {selectedItem ? (
            (() => {
              const isFloAuth = selectedItem.protectionType === 'floauth';
              const isRoleBased = selectedItem.protectionType === 'roles' 
                || (isFloAuth && selectedItem.allowedRoles && selectedItem.allowedRoles.length > 0)
                || tempRoleBasedState[selectedItem.id] === true;
              
              return (
                <div className="flex-1 overflow-y-auto">
                  
                  {/* Header Info */}
              <div className="p-5 border-b border-slate-200">
                <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Valittu Artboard</div>
                <h2 className="text-[16px] font-bold text-slate-800 leading-tight mb-1">{selectedItem.title}</h2>
                <div className="text-[#b80048] text-xs truncate">/wp-admin/post.php?post={selectedItem.id.split('_')[1]}&action=edit</div>
              </div>

              {/* Properties Box */}
              <div className="p-5 border-b border-slate-200 bg-white">
                <div className="flex justify-between items-center mb-5">
                  <span className="text-slate-500 text-sm font-medium">Tyypin Tunniste:</span>
                  <span className="font-bold text-slate-700 uppercase bg-slate-100 px-2.5 py-1 rounded-md text-[11px] tracking-wider">{selectedItem.type}</span>
                </div>
                
                <div className="flex flex-col gap-2 mb-6">
                  <span className="text-slate-700 font-semibold text-sm">1. Sivun Näkyvyys</span>
                  <div className="flex bg-slate-100 p-1 rounded-md border border-slate-200">
                    <button 
                      onClick={() => {
                        if (selectedItem.protectionType !== 'none') {
                          saveProtectionType(selectedItem.id, 'none');
                        }
                      }}
                      className={`flex-1 text-center py-2 text-xs font-bold rounded transition-all ${selectedItem.protectionType === 'none' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500 hover:text-slate-700'}`}
                    >
                      Julkinen
                    </button>
                    <button 
                      onClick={() => {
                        if (selectedItem.protectionType === 'none') {
                          saveProtectionType(selectedItem.id, 'logged_in');
                        }
                      }}
                      className={`flex-1 text-center py-2 text-xs font-bold rounded transition-all ${selectedItem.protectionType !== 'none' ? 'bg-[#b80048]/5 shadow-sm text-[#96003a] border border-[#b80048]/20' : 'text-slate-500 hover:text-slate-700'}`}
                    >
                      Suojattu
                    </button>
                  </div>
                </div>

                {selectedItem.protectionType !== 'none' && (
                  <div className="flex flex-col gap-6 animate-in fade-in slide-in-from-top-2">
                    {/* Vaihe 2: Kirjautumisjärjestelmä */}
                    <div className="flex flex-col gap-2">
                      <span className="text-slate-700 font-semibold text-sm">2. Kirjautumisjärjestelmä</span>
                      <div className={`grid gap-2 ${window.pgmVisibilityApi?.hasFloAuth ? 'grid-cols-2' : 'grid-cols-1'}`}>
                        <button
                          onClick={() => {
                            if (selectedItem.protectionType === 'floauth') {
                              saveProtectionType(selectedItem.id, 'logged_in');
                            }
                          }}
                          className={`flex items-center justify-center py-2.5 px-2 text-xs font-bold border rounded-md transition-all ${selectedItem.protectionType !== 'floauth' ? 'bg-blue-50 border-blue-200 text-blue-700 shadow-sm' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'}`}
                        >
                          Perus WP
                        </button>
                        
                        {window.pgmVisibilityApi?.hasFloAuth && (
                          <button
                            onClick={() => {
                              if (selectedItem.protectionType !== 'floauth') {
                                saveProtectionType(selectedItem.id, 'floauth');
                              }
                            }}
                            className={`flex items-center justify-center py-2.5 px-2 text-xs font-bold border rounded-md transition-all ${selectedItem.protectionType === 'floauth' ? 'bg-[#b80048]/5 border-[#b80048]/20 text-[#96003a] shadow-sm' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'}`}
                          >
                            FloMembers
                          </button>
                        )}
                      </div>
                    </div>

                    {/* Vaihe 3: Oikeusvaatimus (Kaikki järjestelmät) */}
                    <div className="flex flex-col gap-2 animate-in fade-in slide-in-from-top-1">
                      <span className="text-slate-700 font-semibold text-sm">3. Oikeusvaatimus</span>
                      <div className="flex flex-col gap-2">
                        <label className={`flex items-center p-3 border rounded-md cursor-pointer transition-all ${!isRoleBased ? 'bg-indigo-50 border-indigo-200 ring-1 ring-indigo-500' : 'bg-white border-slate-200 hover:border-slate-300'}`}>
                          <input 
                            type="radio" 
                            name="accessLevel" 
                            className="mr-3 w-4 h-4 text-indigo-600 focus:ring-indigo-500" 
                            checked={!isRoleBased} 
                            onChange={() => {
                              setTempRoleBasedState(prev => ({ ...prev, [selectedItem.id]: false }));
                              const newType = selectedItem.protectionType === 'floauth' ? 'floauth' : 'logged_in';
                              saveProtectionTypeAndRoles(selectedItem.id, newType, []);
                            }} 
                          />
                          <div className="flex flex-col">
                            <span className={`text-sm font-bold ${!isRoleBased ? 'text-indigo-900' : 'text-slate-700'}`}>Normaali Jäsensivu</span>
                            <span className="text-[10px] text-slate-500 mt-0.5">Kaikki kirjautuneet (WP tai FloMembers) pääsevät.</span>
                          </div>
                        </label>

                        <label className={`flex items-center p-3 border rounded-md cursor-pointer transition-all ${isRoleBased ? 'bg-amber-50 border-amber-200 ring-1 ring-amber-500' : 'bg-white border-slate-200 hover:border-slate-300'}`}>
                          <input 
                            type="radio" 
                            name="accessLevel" 
                            className="mr-3 w-4 h-4 text-amber-600 focus:ring-amber-500" 
                            checked={isRoleBased} 
                            onChange={() => {
                              setTempRoleBasedState(prev => ({ ...prev, [selectedItem.id]: true }));
                              const newType = selectedItem.protectionType === 'floauth' ? 'floauth' : 'roles';
                              saveProtectionTypeAndRoles(selectedItem.id, newType, selectedItem.allowedRoles || []);
                            }} 
                          />
                          <div className="flex flex-col">
                            <span className={`text-sm font-bold ${isRoleBased ? 'text-amber-900' : 'text-slate-700'}`}>Roolipohjainen</span>
                            <span className="text-[10px] text-slate-500 mt-0.5">Vaadi tiettyjä rooleja (valitaan alla).</span>
                          </div>
                        </label>
                      </div>
                    </div>

                    {/* Salasanasuojattu tila (readonly fallback) */}
                    {selectedItem.protectionType === 'password' && (
                      <div className="mt-2 p-3 bg-purple-50 border border-purple-200 rounded-md">
                        <span className="text-xs font-bold text-purple-800 flex items-center">
                          WP Salasanasuojattu
                        </span>
                        <p className="text-[10px] text-purple-600 mt-1">Tämä sivu on suojattu suoraan WordPressin omalla salasanatoiminnolla sivun muokkausnäkymässä.</p>
                      </div>
                    )}
                  </div>
                )}
              </div>

              {/* Roles Section */}
              {isRoleBased && (
                <div className="p-5 border-b border-slate-200 bg-white animate-in fade-in slide-in-from-top-1">
                  <h3 className="font-semibold text-slate-800 text-sm mb-1">Aliroolien Pääsyoikeudet (Sivu & Kansio)</h3>
                  <p className="text-[11px] text-slate-500 mb-4 leading-relaxed">Määritä, mille alirooleille tämä sivu (sekä linkitetty mediakansio) näytetään sivustolla.</p>
                  
                  <div className="space-y-2">
                    {subRoles.map(role => {
                      const isAdministrator = role.id === 'administrator';
                      const isChecked = isAdministrator || selectedItem.allowedRoles.includes(role.id);
                      
                      let containerClasses = "flex items-center justify-between p-3 rounded-md border transition-all ";
                      let iconClasses = "w-5 h-5 rounded flex items-center justify-center border transition-colors ";
                      
                      if (isAdministrator) {
                         containerClasses += "cursor-not-allowed bg-slate-50 border-slate-200 text-slate-500 opacity-80";
                         iconClasses += "bg-slate-300 border-slate-300 text-white";
                      } else if (isChecked) {
                         containerClasses += "cursor-pointer bg-[#b80048]/5 border-[#b80048]/30 text-[#6d0831] shadow-sm";
                         iconClasses += "bg-[#b80048] border-[#b80048] text-white";
                      } else {
                         containerClasses += "cursor-pointer bg-white border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50";
                         iconClasses += "bg-slate-100 border-slate-200 text-transparent";
                      }
                      
                      return (
                        <div 
                          key={role.id}
                          onClick={() => {
                            if (!isAdministrator) saveItemRoles(selectedItem.id, role.id, !isChecked);
                          }}
                          className={containerClasses}
                        >
                          <span className="font-medium text-sm">{role.name}</span>
                          <div className={iconClasses}>
                            <Check className="w-3.5 h-3.5" />
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}

              {/* No Access Message Section */}
              {isRoleBased && (
                <div className="p-5 bg-slate-50 border-t border-slate-200 rounded-br-lg animate-in fade-in slide-in-from-top-1">
                  <h3 className="font-semibold text-slate-800 text-sm mb-1">Ei käyttöoikeutta -viesti</h3>
                  <p className="text-[11px] text-slate-500 mb-3 leading-relaxed">Tämä viesti näytetään kirjautuneille käyttäjille, joilla ei ole vaadittua roolia tälle sivulle.</p>
                  <textarea
                    className="w-full border border-slate-200 rounded-md p-3 text-sm bg-white focus:outline-none focus:border-[#b80048] focus:ring-1 focus:ring-[#b80048] transition shadow-sm resize-y"
                    rows="4"
                    placeholder="Esim. Tämä sisältö on vain hallituksen jäsenille..."
                    value={selectedItem.noAccessMessage || ''}
                    onChange={(e) => {
                      const val = e.target.value;
                      setItems(prev => prev.map(i => i.id === selectedItem.id ? { ...i, noAccessMessage: val } : i));
                    }}
                    onBlur={(e) => saveNoAccessMessage(selectedItem.id, e.target.value)}
                  ></textarea>
                </div>
              )}

                </div>
              );
            })()
          ) : (
            <div className="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-400">
              <Monitor className="w-12 h-12 mb-4 opacity-20" />
              <p>Valitse taso tai kohde vasemmalta tai Canvas-alueelta muokataksesi sen ominaisuuksia.</p>
            </div>
          )}
        </aside>

      </div>

      {/* Global Modal for Folder Selection */}
      {activeCardOverlay && (() => {
        const item = items.find(i => i.id === activeCardOverlay);
        if (!item) return null;
        return (
          <div 
            className="fixed inset-0 z-[99999] flex items-center justify-center bg-slate-900/30 animate-in fade-in duration-200"
            onClick={() => setActiveCardOverlay(null)}
          >
            <div 
              className="bg-white rounded-xl shadow-[0_20px_50px_-12px_rgba(0,0,0,0.5)] w-[460px] max-h-[85vh] flex flex-col animate-in zoom-in-95 duration-300 ease-out overflow-hidden"
              onClick={e => e.stopPropagation()}
            >
              <div className="p-6 pb-4 border-b border-slate-100">
                <div className="flex justify-between items-center mb-3">
                  <div className="flex items-center text-[#b80048] font-bold text-[11px] uppercase tracking-wider">
                    <Folder className="w-4 h-4 mr-2" />
                    Site Media Folders
                  </div>
                  <button 
                    onClick={() => setActiveCardOverlay(null)}
                    className="text-slate-400 hover:text-slate-700 p-1.5 bg-slate-100 hover:bg-slate-200 rounded-full transition-colors"
                  >
                    <X className="w-4 h-4" />
                  </button>
                </div>
                
                <h3 className="text-lg font-bold text-slate-800 mb-1">Liitetyt Mediakansiot</h3>
                <p className="text-xs text-slate-500 leading-relaxed">
                  Valitse ne mediakansiot, jotka saavat automaattisesti samat oikeudet kuin 
                  <span className="font-semibold text-slate-700 ml-1">"{item.title}"</span>.
                </p>
              </div>
              
              <div className="flex-1 overflow-y-auto bg-white p-2" style={{ minHeight: '300px' }}>
                {availableFolders.map(f => {
                  const isLinked = (item.linkedFolders || []).includes(f.id);
                  return (
                    <label 
                      key={f.id}
                      className={`flex items-center px-4 py-2.5 mb-0.5 cursor-pointer transition-all rounded-md group ${isLinked ? 'bg-[#b80048]/5/50 border border-[#b80048]/20 shadow-sm text-[#96003a]' : 'border border-transparent hover:bg-slate-50 text-slate-700'}`}
                      style={{ marginLeft: `${(f.depth || 0) * 16}px` }}
                    >
                      <input 
                        type="checkbox" 
                        className="w-4 h-4 text-[#b80048] rounded border-slate-300 focus:ring-[#b80048] mr-3 shadow-sm transition-colors"
                        checked={isLinked}
                        onChange={(e) => toggleLinkedFolder(item.id, f.id, e.target.checked)}
                      />
                      <Folder className={`w-4 h-4 mr-2.5 shrink-0 transition-colors ${isLinked ? 'text-[#b80048]' : 'text-slate-400 group-hover:text-slate-500'}`} />
                      <span className={`text-[13px] truncate ${isLinked ? 'font-bold' : 'font-medium'}`}>
                        {f.title}
                      </span>
                    </label>
                  );
                })}
              </div>

              <div className="p-5 border-t border-slate-100 bg-slate-50">
                <button
                  onClick={() => setActiveCardOverlay(null)}
                  className="w-full bg-[#b80048] text-white font-bold py-3 rounded-lg hover:bg-[#96003a] hover:shadow-lg transition-all text-sm uppercase tracking-wider"
                >
                  Valmis
                </button>
              </div>
            </div>
          </div>
        );
      })()}

    </div>
  );
}
