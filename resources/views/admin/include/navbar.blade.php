   @php
       $currentAdmin = Auth::guard('admin')->user();
   @endphp
   <!-- Navbar -->
   <header class="navbar">
       <button class="toggle-btn" id="toggleBtn">
           <div class="hamburger">
               <span></span>
               <span></span>
               <span></span>
           </div>
       </button>

       <div class="search-bar">
           <input type="text" placeholder="Search here..." />
           <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2">
               <circle cx="11" cy="11" r="8"></circle>
               <path d="m21 21-4.35-4.35"></path>
           </svg>
       </div>

       <div class="navbar-actions">
           <div class="dropdown" id="userDropdown">
               <div class="user-profile">
                   <div class="w-10 h-10 flex items-center justify-center rounded-full bg-[var(--primary-color)] text-white font-semibold">
                       {{ strtoupper(substr($currentAdmin->name, 0, 2)) }}
                   </div>

                   <div class="user-info">
                       <div class="user-name">{{ \Str::limit($currentAdmin->name, 15) }}</div>
                       <div class="user-role">{{ $currentAdmin->getRoleName() }}</div>
                   </div>
               </div>
               <div class="dropdown-menu">
                   <div class="dropdown-header">
                       <div class="user-name">{{ $currentAdmin->name }}</div>
                       <div class="user-role">{{ $currentAdmin->email }}</div>
                   </div>
                   <a href="{{ route('admin.profile') }}" class="dropdown-item">
                       <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                           stroke-width="2">
                           <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                           <circle cx="12" cy="7" r="4"></circle>
                       </svg>
                       My Profile
                   </a>

                   <a href="{{ route('admin.profile') }}" class="dropdown-item">
                       <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                           stroke-width="2">
                           <circle cx="12" cy="12" r="3"></circle>
                           <path d="M12 1v6m0 6v6"></path>
                       </svg>
                       Account Settings
                   </a>

                   @if($currentAdmin->isSuperAdmin())
                   <a href="{{ route('admin.user.index') }}" class="dropdown-item">
                       <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                           stroke-width="2">
                           <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                           <circle cx="9" cy="7" r="4"></circle>
                           <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                           <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                       </svg>
                       Manage Users
                   </a>
                   @endif

                   <form action="{{ route('admin.logout') }}" method="post" class="w-full">
                       @csrf
                       <button type="submit" class="dropdown-item logout w-full cursor-pointer">
                           <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                               stroke="currentColor" stroke-width="2">
                               <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                               <polyline points="16 17 21 12 16 7"></polyline>
                               <line x1="21" y1="12" x2="9" y2="12"></line>
                           </svg>
                           Logout
                       </button>
                   </form>
               </div>
           </div>
       </div>
   </header>
