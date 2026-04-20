  @php
      $sidebarAdmin = Auth::guard('admin')->user();
      $currentRoute = Route::currentRouteName();

      // Helper function to check if route matches
      $isActive = fn($routes) => collect((array) $routes)->contains(fn($r) => str_starts_with($currentRoute, $r));

      // Define route groups for submenus
      $runCampaignRoutes = [
          'admin.campaign.create',
          'admin.sidebar.campaign.create',
          'admin.hidden.link.campaign.create',
      ];
      $reportingRoutes = [
          'admin.campaign.index',
          'admin.campaign.show',
          'admin.sidebar.campaign.index',
          'admin.sidebar.campaign.show',
          'admin.hidden.link.campaign.index',
          'admin.hidden.link.campaign.show',
          'admin.sticky.campaign',
      ];
      $domainRoutes = ['admin.set', 'admin.select.category', 'admin.domain', 'admin.redirect'];
      $articleAddRoutes = [
          'admin.articles.opt',
          'admin.articles.category',
          'admin.articles.language',
          'admin.articles.upload',
      ];
      $dripfeedRoutes = ['admin.schedule.campaign', 'admin.schedule.sticky.campaign', 'admin.schedule.sidebar.campaign', 'admin.wp.schedule.campaign'];
      $userRoutes = ['admin.user'];
  @endphp
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
      <a class="sidebar-logo" href="{{ route('admin.dashboard') }}">
          <img src="{{ asset('favicon.png') }}" class="logo-icon" alt="" />
          <span class="logo-text">Diamond Pbn</span>
      </a>

      <nav class="sidebar-menu">
          <div class="menu-section flex flex-col">
              <div class="menu-title order-1 shrink-0">Main</div>
              <a href="{{ route('admin.dashboard') }}"
                  class="menu-item order-3 lg:order-2 {{ $currentRoute === 'admin.dashboard' ? 'active' : '' }}">
                  <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="3" y="3" width="7" height="7"></rect>
                      <rect x="14" y="3" width="7" height="7"></rect>
                      <rect x="14" y="14" width="7" height="7"></rect>
                      <rect x="3" y="14" width="7" height="7"></rect>
                  </svg>
                  <span class="menu-text">Dashboard</span>
              </a>

              {{-- Run Campaigns: only Super Admin and Admin can create; Members can only add articles --}}
              @if ($sidebarAdmin->canCreateCampaigns())
              <div class="menu-item side-menu-btn order-2 lg:order-3 {{ $isActive($runCampaignRoutes) ? 'active' : '' }}"
                  data-submenu-open="{{ $isActive($runCampaignRoutes) ? 'true' : 'false' }}">
                  <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                      <polyline points="7 10 12 15 17 10"></polyline>
                      <line x1="12" y1="15" x2="12" y2="3"></line>
                  </svg>
                  <span class="menu-text">Run Campaigns</span>
                  <svg class="menu-arrow {{ $isActive($runCampaignRoutes) ? 'rotate-90' : '' }}" width="16"
                      height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
              </div>
              <div class="submenu order-2 lg:order-3 {{ $isActive($runCampaignRoutes) ? 'open' : '' }}">
                  <a href="{{ route('admin.campaign.create') }}"
                      class="submenu-item {{ $currentRoute === 'admin.campaign.create' ? 'active' : '' }}">PBN Post</a>
                  <a href="{{ route('admin.sidebar.campaign.create') }}"
                      class="submenu-item {{ $currentRoute === 'admin.sidebar.campaign.create' ? 'active' : '' }}">PBN
                      Sidebar Post</a>
                  <a href="{{ route('admin.hidden.link.campaign.create') }}"
                      class="submenu-item {{ $currentRoute === 'admin.hidden.link.campaign.create' ? 'active' : '' }}">PBN
                      Hidden Links</a>
              </div>
              @endif

              {{-- Reporting: only for users who can create campaigns (Members cannot see campaigns) --}}
              @if ($sidebarAdmin->canCreateCampaigns())
              <div class="menu-item side-menu-btn order-4 {{ $isActive($reportingRoutes) ? 'active' : '' }}"
                  data-submenu-open="{{ $isActive($reportingRoutes) ? 'true' : 'false' }}">
                  <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                  </svg>
                  <span class="menu-text">Reporting </span>
                  <svg class="menu-arrow {{ $isActive($reportingRoutes) ? 'rotate-90' : '' }}" width="16"
                      height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
              </div>
              <div class="submenu order-4 {{ $isActive($reportingRoutes) ? 'open' : '' }}">
                  <a href="{{ route('admin.campaign.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.campaign.') && $currentRoute !== 'admin.campaign.create' ? 'active' : '' }}">PBN
                      Post</a>
                  <a href="{{ route('admin.sidebar.campaign.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.sidebar.campaign.') && $currentRoute !== 'admin.sidebar.campaign.create' ? 'active' : '' }}">Blogroll
                  </a>
                  <a href="{{ route('admin.hidden.link.campaign.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.hidden.link.campaign.') && $currentRoute !== 'admin.hidden.link.campaign.create' ? 'active' : '' }}">Hidden
                      Link</a>
                  <a href="{{ route('admin.sticky.campaign.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.sticky.campaign.') && $currentRoute !== 'admin.sticky.campaign.create' ? 'active' : '' }}">Sticky
                      Post</a>
              </div>
              @endif

              <div class="menu-item side-menu-btn order-5 {{ $isActive($domainRoutes) ? 'active' : '' }}"
                  data-submenu-open="{{ $isActive($domainRoutes) ? 'true' : 'false' }}">
                  <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="2" y1="12" x2="22" y2="12"></line>
                      <path
                          d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z">
                      </path>
                  </svg>
                  <span class="menu-text">Domains </span>
                  <svg class="menu-arrow {{ $isActive($domainRoutes) ? 'rotate-90' : '' }}" width="16"
                      height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
              </div>
              <div class="submenu order-5 {{ $isActive($domainRoutes) ? 'open' : '' }}">
                  <a href="{{ route('admin.set.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.set') ? 'active' : '' }}">Domains
                      Set </a>
                  <a href="{{ route('admin.select.category') }}"
                      class="submenu-item {{ $currentRoute === 'admin.select.category' ? 'active' : '' }} !flex justify-between">Domains
                      List </a>
                  <a href="{{ route('admin.domain.create') }}"
                      class="submenu-item {{ $currentRoute === 'admin.domain.create' ? 'active' : '' }} !flex justify-between">Add
                      Domains</a>
                  <a href="{{ route('admin.domain.category.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.domain.category') ? 'active' : '' }} !flex justify-between">Category</a>
              </div>
          </div>

          {{-- articles section --}}
          <div class="menu-section ">
              <div class="menu-title">Articles</div>

              <a href="{{ route('admin.article.index') }}"
                  class="menu-item {{ str_starts_with($currentRoute, 'admin.article.') && ! str_starts_with($currentRoute, 'admin.article.trashed') ? 'active' : '' }}">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="menu-icon">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                      <polyline points="14 2 14 8 20 8"></polyline>
                      <line x1="16" y1="13" x2="8" y2="13"></line>
                      <line x1="16" y1="17" x2="8" y2="17"></line>
                      <polyline points="10 9 9 9 8 9"></polyline>
                  </svg>
                  <span class="menu-text">Articles</span>
              </a>

              <a href="{{ route('admin.article.trashed.index') }}"
                  class="menu-item {{ str_starts_with($currentRoute, 'admin.article.trashed') ? 'active' : '' }}">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="menu-icon">
                      <path d="M3 6h18"></path>
                      <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                      <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                      <line x1="10" y1="11" x2="10" y2="17"></line>
                      <line x1="14" y1="11" x2="14" y2="17"></line>
                  </svg>
                  <span class="menu-text">Deleted used articles</span>
              </a>

              <div class="menu-item side-menu-btn {{ $isActive($articleAddRoutes) ? 'active' : '' }}"
                  data-submenu-open="{{ $isActive($articleAddRoutes) ? 'true' : 'false' }}">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="menu-icon">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                      <polyline points="14 2 14 8 20 8"></polyline>
                      <line x1="12" y1="18" x2="12" y2="12"></line>
                      <line x1="9" y1="15" x2="15" y2="15"></line>
                  </svg>
                  <span class="menu-text ">Add Articles</span>
                  <svg class="menu-arrow {{ $isActive($articleAddRoutes) ? 'rotate-90' : '' }}" width="16"
                      height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
              </div>
              <div class="submenu {{ $isActive($articleAddRoutes) ? 'open' : '' }}">
                  <a href="{{ route('admin.articles.opt') }}"
                      class="submenu-item {{ $currentRoute === 'admin.articles.opt' ? 'active' : '' }}">Add
                      Articles</a>
                  <a href="{{ route('admin.articles.category.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.articles.category') ? 'active' : '' }}">Category</a>
                  <a href="{{ route('admin.articles.language.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.articles.language') ? 'active' : '' }}">Language</a>
              </div>

              <a href="{{ route('admin.articles.set.index') }}"
                  class="menu-item {{ str_starts_with($currentRoute, 'admin.articles.set') ? 'active' : '' }}">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="menu-icon">
                      <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                      <line x1="9" y1="13" x2="15" y2="13"></line>
                      <line x1="9" y1="17" x2="15" y2="17"></line>
                  </svg>
                  <span class="menu-text">Article Set</span>
              </a>
          </div>

          <div class="menu-section ">
              <div class="menu-title">Addons</div>
              @if ($sidebarAdmin->canCreateCampaigns())
              <a href="{{ route('admin.sticky.campaign.create') }}"
                  class="menu-item {{ $currentRoute === 'admin.sticky.campaign.create' ? 'active' : '' }}">
                  <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                      <line x1="9" y1="3" x2="9" y2="21"></line>
                  </svg>
                  <span class="menu-text">Sticky</span>
              </a>

              <div class="menu-item side-menu-btn {{ $isActive($dripfeedRoutes) ? 'active' : '' }}"
                  data-submenu-open="{{ $isActive($dripfeedRoutes) ? 'true' : 'false' }}">
                  <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <polyline points="12 6 12 12 16 14"></polyline>
                  </svg>
                  <span class="menu-text ">DripFeed</span>
                  <svg class="menu-arrow {{ $isActive($dripfeedRoutes) ? 'rotate-90' : '' }}" width="16"
                      height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
              </div>
              <div class="submenu {{ $isActive($dripfeedRoutes) ? 'open' : '' }}">
                  <a href="{{ route('admin.schedule.campaign.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.schedule.campaign') && !str_starts_with($currentRoute, 'admin.wp.schedule') && !str_starts_with($currentRoute, 'admin.schedule.sticky.campaign') ? 'active' : '' }}">Schedule
                      Post</a>
                  <a href="{{ route('admin.schedule.sticky.campaign.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.schedule.sticky.campaign') ? 'active' : '' }}">Schedule Sticky Post</a>
                  <a href="{{ route('admin.wp.schedule.campaign.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.wp.schedule.campaign') ? 'active' : '' }}">WP Scheduled</a>
                  <a href="{{ route('admin.schedule.sidebar.campaign.index') }}"
                      class="submenu-item {{ str_starts_with($currentRoute, 'admin.schedule.sidebar.campaign') ? 'active' : '' }}">
                      <div class="w-full !flex items-center gap-1 relative">Schedule Blogroll
                         {{-- <span --}}
                              {{-- class="menu-badge !text-[8px] absolute -top-2 -right-1">SOON</span> --}}

                      </div>
                  </a>
              </div>
              @endif
          </div>

          <div class="menu-section ">
              <div class="menu-title">Profile</div>

              {{-- User Management - Only for Super Admin and Admin --}}
              @if ($sidebarAdmin->isSuperAdmin() || $sidebarAdmin->isAdmin())
                  <div class="menu-item side-menu-btn {{ $isActive($userRoutes) ? 'active' : '' }}"
                      data-submenu-open="{{ $isActive($userRoutes) ? 'true' : 'false' }}">
                      <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                          stroke-width="2">
                          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                          <circle cx="9" cy="7" r="4"></circle>
                          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                      </svg>
                      <span class="menu-text ">Users</span>
                      <svg class="menu-arrow {{ $isActive($userRoutes) ? 'rotate-90' : '' }}" width="16"
                          height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="9 18 15 12 9 6"></polyline>
                      </svg>
                  </div>
                  <div class="submenu {{ $isActive($userRoutes) ? 'open' : '' }}">
                      <a href="{{ route('admin.user.index') }}"
                          class="submenu-item {{ $currentRoute === 'admin.user.index' ? 'active' : '' }}">All
                          Users</a>
                      @if ($sidebarAdmin->isSuperAdmin())
                          <a href="{{ route('admin.user.create') }}"
                              class="submenu-item {{ $currentRoute === 'admin.user.create' ? 'active' : '' }}">Create
                              User</a>
                      @endif
                  </div>
              @endif

              {{-- My Profile --}}
              <a href="{{ route('admin.profile') }}"
                  class="menu-item {{ $currentRoute === 'admin.profile' ? 'active' : '' }}">
                  <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                      <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  <span class="menu-text">My Profile</span>
              </a>

              <a href="{{ route('admin.profile') }}" class="menu-item">
                  <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="3"></circle>
                      <path
                          d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z">
                      </path>
                  </svg>
                  <span class="menu-text">Settings</span>
              </a>

              {{-- Logout Button --}}
              <form action="{{ route('admin.logout') }}" method="POST" class="w-full">
                  @csrf
                  <button type="submit" class="menu-item w-full cursor-pointer"
                      style="background: var(--primary-color)">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                          class="menu-icon !text-white" stroke="currentColor" stroke-width="2">
                          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                          <polyline points="16 17 21 12 16 7"></polyline>
                          <line x1="21" y1="12" x2="9" y2="12"></line>
                      </svg>
                      <span class="menu-text !text-white">Logout</span>
                  </button>
              </form>
          </div>
      </nav>
  </aside>

  <style>
      .submenu.open {
          display: block;
      }

      .menu-arrow.rotate-90 {
          transform: rotate(90deg);
      }

      .submenu-item.active {
          background: #ffffff26;
          color: white;
      }

      .menu-item.active {
          background: rgba(255, 74, 23, 0.1);
          border-left: 3px solid var(--primary-color);
      }

   
  </style>
