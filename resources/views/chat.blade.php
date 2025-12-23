@php
    $mqttHost = $mqttConfig['host'] ?? 'localhost';
    $mqttWsPort = $mqttConfig['ws_port'] ?? 9001;
    $mqttUseTls = $mqttConfig['use_tls'] ?? false;
    $mqttTopic = $activeTopic;
    $clientIdPrefix = $mqttConfig['client_id_prefix'] ?? 'laravel-chat-';
    $currentUser = $currentUser ?? 'User';
    $initialMessages = $messages ?? collect();
    $rooms = $rooms ?? collect();
@endphp

<!doctype html>
<html
  lang="en"
  class="layout-navbar-fixed layout-menu-fixed layout-compact"
  dir="ltr"
  data-skin="default"
  data-assets-path="{{ asset('assets/') }}/"
  data-template="horizontal-menu-template-no-customizer"
  data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Chat App - MQTT</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&ampdisplay=swap" rel="stylesheet" />

    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/iconify-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/node-waves/node-waves.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/maxLength/maxLength.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/app-chat.css') }}" />
    <style>
      /* Make main content container fill viewport on chat page */
      .container-xxl.flex-grow-1.container-p-y {
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
      }

      .app-chat .app-chat-contacts .sidebar-body .chat-contact-list li.active,
      .app-chat .app-chat-sidebar-left .sidebar-body .chat-contact-list li.active {
        background-color: rgba(var(--bs-primary-rgb), 0.12);
        box-shadow: none;
        color: var(--bs-heading-color);
      }

      .app-chat .app-chat-contacts .sidebar-body .chat-contact-list li.active h6,
      .app-chat .app-chat-contacts .sidebar-body .chat-contact-list li.active .chat-contact-list-item-time,
      .app-chat .app-chat-sidebar-left .sidebar-body .chat-contact-list li.active h6,
      .app-chat .app-chat-sidebar-left .sidebar-body .chat-contact-list li.active .chat-contact-list-item-time {
        color: var(--bs-heading-color);
      }

      /* Attachment bubble styling */
      .chat-message-text .chat-attachment {
        display: inline-flex;
        align-items: center;
        padding: 0.35rem 0.75rem;
        border-radius: 0.375rem;
        background-color: rgba(255, 255, 255, 0.16);
      }

      .chat-message:not(.chat-message-right) .chat-message-text .chat-attachment {
        background-color: rgba(var(--bs-primary-rgb), 0.06);
      }

      .chat-attachment-link {
        display: inline-flex;
        align-items: center;
        text-decoration: none;
      }

      .chat-message.chat-message-right .chat-attachment-link {
        color: #fff;
        font-weight: 500;
      }

      .chat-message:not(.chat-message-right) .chat-attachment-link {
        color: var(--bs-heading-color);
        font-weight: 500;
      }

      .chat-attachment-icon {
        margin-right: 0.5rem;
        font-size: 1.1rem;
      }

      .chat-attachment-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 240px;
      }

      /* Stretch chat container to full viewport height */
      .content-wrapper {
        justify-content: flex-start !important;
      }

      .app-chat {
        block-size: 100vh !important;
        inline-size: 100% !important;
      }

      .app-chat.card {
        margin-bottom: 0;
        border-radius: 0;
      }

      .app-chat .app-chat-history,
      .app-chat .app-chat-conversation,
      .app-chat .app-chat-contacts,
      .app-chat .app-chat-sidebar-left,
      .app-chat .app-chat-sidebar-right {
        block-size: 100vh !important;
      }

      .app-chat .chat-history-wrapper {
        display: flex;
        flex-direction: column;
        block-size: 100%;
      }

      .app-chat .app-chat-history .chat-history-body {
        flex: 1 1 auto;
        block-size: auto !important;
      }

      .app-chat .app-chat-history .chat-history-footer {
        margin-bottom: 1.75rem;
        /* padding-top: 0.75rem;
        padding-bottom: 1.75rem; */
      }
    </style>

    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
  </head>

  <body>
    @php
      $authUser = auth()->user();
      $avatarUrl = $authUser?->avatar_url ?? asset('assets/img/avatars/1.png');
    @endphp
    <div class="layout-wrapper layout-navbar-full layout-horizontal layout-without-menu">
      <div class="layout-container">
        <!-- Layout container -->
        <div class="layout-page">
          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Content -->
            <div class="container-xxl flex-grow-1 container-p-y">
              <div class="app-chat card overflow-hidden">
                <div class="row g-0">
                  <!-- Sidebar Left-->
                  <div class="col app-chat-sidebar-left app-sidebar flex-grow-0 overflow-hidden border-end" id="app-chat-sidebar-left">
                    <div class="chat-sidebar-left-user sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-6 pt-12">
                      <div class="avatar avatar-xl avatar-online">
                        <img src="{{ $avatarUrl }}" alt="Avatar" class="rounded-circle" />
                      </div>
                      <h5 class="mt-4 mb-0">{{ auth()->user()->username ?? 'User' }}</h5>
                      <span>User</span>
                      <div class="d-flex align-items-center mt-2">
                        <i class="icon-base ri ri-check-line text-success me-1"></i><small class="text-success">Online</small>
                      </div>
                    </div>
                    <div class="sidebar-body px-6 pb-6 pt-2">
                      <div class="my-6">
                        <p class="text-uppercase text-body-secondary mb-1">Profile photo</p>
                        <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data">
                          @csrf
                          <div class="input-group input-group-sm">
                            <input type="file" name="avatar" accept="image/*" class="form-control" required />
                            <button type="submit" class="btn btn-primary">Upload</button>
                          </div>
                          @error('avatar')
                            <small class="text-danger d-block mt-1">{{ $message }}</small>
                          @enderror
                          @if (session('status') === 'Profile photo updated.')
                            <small class="text-success d-block mt-1">Photo updated.</small>
                          @endif
                        </form>
                      </div>
                      <div class="d-flex mt-6">
                        <form method="POST" action="{{ route('logout') }}" class="w-100">
                          @csrf
                          <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center">
                            Logout<i class="icon-base ri ri-logout-box-r-line icon-xs ms-1"></i>
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                  <!-- /Sidebar Left-->

                  <!-- Chat & Contacts -->
                  <div class="col app-chat-contacts app-sidebar flex-grow-0 overflow-hidden border-end" id="app-chat-contacts">
                    <div class="sidebar-header px-5 border-bottom d-flex align-items-center">
                      <div class="d-flex align-items-center me-6 me-lg-0">
                        <div class="flex-shrink-0 avatar avatar-online me-4" data-bs-toggle="sidebar" data-overlay="app-overlay-ex" data-target="#app-chat-sidebar-left">
                          <img class="user-avatar rounded-circle cursor-pointer" src="{{ $avatarUrl }}" alt="Avatar" />
                        </div>
                        <div class="flex-grow-1 input-group input-group-sm input-group-merge rounded-pill">
                          <span class="input-group-text" id="basic-addon-search31"><i class="icon-base ri ri-search-line icon-20px"></i></span>
                          <input type="text" class="form-control chat-search-input" placeholder="Search..." aria-label="Search..." aria-describedby="basic-addon-search31" />
                        </div>
                      </div>
                      <i class="icon-base ri ri-close-line icon-lg cursor-pointer position-absolute top-50 end-0 translate-middle d-lg-none d-block" data-overlay data-bs-toggle="sidebar" data-target="#app-chat-contacts"></i>
                    </div>
                    <div class="sidebar-body">
                      <ul class="list-unstyled chat-contact-list py-2 mb-0" id="chat-list">
                        <li class="chat-contact-list-item chat-contact-list-item-title mt-0 d-flex justify-content-between align-items-center px-4">
                          <h5 class="text-primary mb-0">Rooms</h5>
                          <button
                            type="button"
                            class="btn btn-sm btn-icon btn-text-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#addRoomModal"
                            title="Add room"
                          >
                            <i class="icon-base ri ri-add-line icon-20px"></i>
                          </button>
                        </li>
                        @forelse($rooms as $room)
                          <li
                            class="chat-contact-list-item mb-1 {{ $room->topic === $mqttTopic ? 'active' : '' }}"
                            data-room-topic="{{ $room->topic }}"
                            data-room-name="{{ $room->name }}"
                          >
                            @php
                              $roomLinkParams = ['topic' => $room->topic];
                              if (!empty($mqttTopic) && $mqttTopic !== $room->topic) {
                                  $roomLinkParams['mark_read'] = $mqttTopic;
                              }
                            @endphp
                            <a class="d-flex align-items-center" href="{{ route('chat.index', $roomLinkParams) }}">
                              <div class="flex-shrink-0 avatar avatar-online">
                                <span class="avatar-initial rounded-circle bg-label-primary">{{ strtoupper(substr($room->name,0,2)) }}</span>
                              </div>
                              <div class="chat-contact-info flex-grow-1 ms-4">
                                <div class="d-flex justify-content-between align-items-center">
                                  <h6 class="chat-contact-name text-truncate fw-normal m-0">
                                    {{ $room->name }}
                                    <span class="text-body-secondary">({{ $room->users_count ?? 0 }})</span>
                                  </h6>
                                  @if(($room->unread_count ?? 0) > 0)
                                    <span
                                      class="badge bg-label-primary rounded-pill ms-2 room-unread-badge"
                                      data-room-topic="{{ $room->topic }}"
                                    >
                                      {{ $room->unread_count }}
                                    </span>
                                  @endif
                                </div>
                                <small class="chat-contact-status text-truncate">{{ $room->topic }}</small>
                              </div>
                            </a>
                          </li>
                        @empty
                          <li class="chat-contact-list-item chat-list-item-0">
                            <h6 class="text-body-secondary mb-0">No Rooms Found</h6>
                          </li>
                        @endforelse
                      </ul>
                    </div>
                  </div>
                  <!-- /Chat contacts -->

                  <!-- Chat History -->
                  <div class="col app-chat-history">
                    <div class="chat-history-wrapper">
                      <div class="chat-history-header border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                          <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 avatar avatar-online d-lg-none d-block me-4" data-bs-toggle="sidebar" data-overlay="app-overlay-ex" data-target="#app-chat-sidebar-left">
                              <img src="{{ $avatarUrl }}" alt="Avatar" class="rounded-circle" />
                            </div>
                            <div class="d-flex flex-column">
                              <h6 class="mb-1">
                                @if ($mqttTopic)
                                  Room: {{ $mqttTopic }}
                                @else
                                  No room selected
                                @endif
                              </h6>
                              {{-- <small class="text-body-secondary">
                                MQTT {{ $mqttHost }}:{{ $mqttWsPort }} (WebSocket)
                              </small> --}}
                            </div>
                          </div>
                          <div class="d-flex align-items-center">
                            <span class="badge bg-label-success me-3" id="mqtt-status">
                              {{ $mqttTopic ? 'Connecting...' : 'No room' }}
                            </span>
                            @if (isset($activeRoom) && $mqttTopic)
                              <div class="d-flex align-items-center">
                                <button
                                  type="button"
                                  class="btn btn-sm btn-text-secondary ms-2"
                                  data-bs-toggle="sidebar"
                                  data-overlay
                                  data-target="#app-chat-sidebar-right"
                                  title="Room info"
                                >
                                  <i class="icon-base ri ri-information-line icon-18px"></i>
                                </button>
                              </div>
                            @endif
                          </div>
                        </div>
                      </div>

                      <div class="chat-history-body ps ps--active-y" id="chat-messages">
                        <button
                          type="button"
                          class="btn btn-sm btn-text-secondary w-100 mb-2 d-none"
                          id="load-older-messages-btn"
                        >
                          Load earlier messages
                        </button>
                        <ul class="list-unstyled chat-history" id="chat-history-list"></ul>
                      </div>

                      <div class="chat-history-footer shadow-xs">
                        <form class="form-send-message d-flex justify-content-between align-items-center" id="chat-form" data-disable-default-send="1">
                          <input class="form-control message-input border-0 me-4 shadow-none" placeholder="Type your message here..." id="message-input" />
                          <div class="message-actions d-flex align-items-center">
                            <label class="form-label mb-0 me-1">
                              <span class="btn btn-text-secondary btn-icon rounded-pill cursor-pointer">
                                <i class="icon-base ri ri-attachment-2 icon-md text-heading"></i>
                              </span>
                              <input type="file" id="chat-file-input" hidden />
                            </label>
                            <span class="badge bg-label-secondary ms-1 d-none" id="chat-file-badge"></span>
                            <button type="submit" class="btn btn-primary d-flex send-msg-btn">
                              <span class="align-middle d-md-inline-block d-none">Send</span>
                              <i class="icon-base ri ri-send-plane-line icon-sm ms-md-2 ms-0"></i>
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                  <!-- /Chat History -->

                  <!-- Sidebar Right -->
                  <div class="col app-chat-sidebar-right app-sidebar overflow-hidden" id="app-chat-sidebar-right">
                    <div class="sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-6 pt-12">
                      @if (isset($activeRoom))
                        <div class="avatar avatar-xl avatar-online chat-sidebar-avatar">
                          <span class="avatar-initial rounded-circle bg-label-primary">
                            {{ strtoupper(substr($activeRoom->name, 0, 2)) }}
                          </span>
                        </div>
                        <h5 class="mt-4 mb-0">{{ $activeRoom->name }}</h5>
                        <span class="text-body-secondary">
                          {{ $activeRoom->topic }}
                        </span>
                        <span class="text-body-secondary small">
                          {{ $activeRoom->users->count() }} member{{ $activeRoom->users->count() === 1 ? '' : 's' }}
                        </span>
                      @else
                        <h5 class="mt-4 mb-0">Room info</h5>
                        <span class="text-body-secondary small">No room selected</span>
                      @endif
                      <i
                        class="icon-base ri ri-close-line icon-24px cursor-pointer close-sidebar d-block"
                        data-bs-toggle="sidebar"
                        data-overlay
                        data-target="#app-chat-sidebar-right"
                      ></i>
                    </div>
                    <div class="sidebar-body p-6 pt-0">
                      <div class="my-6">
                        <p class="text-uppercase mb-1 text-body-secondary">Members</p>
                        <ul class="list-unstyled d-grid gap-3 mb-0 py-2 text-heading">
                          @if (isset($activeRoom) && $activeRoom->users->count())
                            @foreach ($activeRoom->users as $member)
                              <li class="d-flex align-items-center">
                                <div class="avatar avatar-sm">
                                  <span class="avatar-initial rounded-circle bg-label-primary">
                                    {{ strtoupper(substr($member->username, 0, 2)) }}
                                  </span>
                                </div>
                                <div class="ms-3">
                                  <div class="fw-medium">{{ $member->username }}</div>
                                </div>
                              </li>
                            @endforeach
                          @else
                            <li class="text-body-secondary">No members in this room.</li>
                          @endif
                        </ul>
                      </div>

                      @if (isset($activeRoom))
                        @php
                          $inviteUrl = route('rooms.join', $activeRoom);
                        @endphp
                        <div class="my-6">
                          <p class="text-uppercase mb-1 text-body-secondary">Invite link</p>
                          <button
                            type="button"
                            class="btn btn-sm btn-primary"
                            id="copy-invite-link-btn"
                            data-invite-url="{{ $inviteUrl }}"
                          >
                            Copy link
                          </button>
                          <small class="text-body-secondary d-block mt-1 d-none" id="copy-invite-link-feedback">
                            Link copied
                          </small>
                        </div>
                        <div class="my-6">
                          <p class="text-uppercase mb-1 text-body-secondary">Actions</p>
                          <form method="POST" action="{{ route('rooms.leave', $activeRoom) }}" class="leave-room-form">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger w-100 leave-room-btn">
                              Leave room
                            </button>
                          </form>
                        </div>
                      @endif
                    </div>
                  </div>
                  <!-- /Sidebar Right -->

                  <div class="app-overlay"></div>
                </div>
              </div>
            </div>
            <!--/ Content -->

            <!-- Footer -->
            {{-- <footer class="content-footer footer bg-footer-theme">
              <div class="container-xxl">
                <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                  <div class="mb-2 mb-md-0">
                    © <script>document.write(new Date().getFullYear());</script>,
                  </div>
                </div>
              </div>
            </footer> --}}
            <!-- / Footer -->

            <div class="content-backdrop fade"></div>
          </div>
          <!--/ Content wrapper -->
        </div>
        <!--/ Layout container -->
      </div>
    </div>

    <!-- Add Room Modal -->
    <div class="modal fade" id="addRoomModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add room</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="{{ route('rooms.store') }}">
            @csrf
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label" for="room_name">Room name</label>
                <input
                  type="text"
                  id="room_name"
                  name="room_name"
                  class="form-control"
                  placeholder="Room name"
                  value="{{ old('room_name') }}"
                  required
                />
                @error('room_name')
                  <small class="text-danger d-block mt-1">{{ $message }}</small>
                @enderror
              </div>
              <div class="mb-3">
                <label class="form-label" for="room_topic">Topic</label>
                <input
                  type="text"
                  id="room_topic"
                  name="room_topic"
                  class="form-control"
                  placeholder="Contoh: chat/support"
                  value="{{ old('room_topic') }}"
                  required
                />
                @error('room_topic')
                  <small class="text-danger d-block mt-1">{{ $message }}</small>
                @enderror
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-text-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Create room</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
    <div class="drag-target"></div>

    <!-- Core JS -->
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/hammer/hammer.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/i18n/i18n.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('assets/js/main.js') }}"></script>

    <!-- Page JS -->
    <script src="{{ asset('assets/js/app-chat.js') }}"></script>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        @if ($errors->has('room_name') || $errors->has('room_topic'))
          var modalEl = document.getElementById('addRoomModal');
          if (modalEl && window.bootstrap && bootstrap.Modal) {
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
          }
        @endif
      });
    </script>

    <!-- MQTT hookup -->
    <script>
      (function () {
        const mqttHost = @json($mqttHost);
        const mqttWsPort = @json($mqttWsPort);
        const mqttUseTls = @json($mqttUseTls);
        const mqttTopic = @json($mqttTopic);
        const clientIdPrefix = @json($clientIdPrefix);
        const currentUser = @json($currentUser);
        const initialMessages = @json($initialMessages);
        const activeLastReadAt = @json($activeLastReadAt);
        const notificationIcon = @json(asset('assets/img/favicon/favicon.ico'));
        const selfAvatarUrl = @json($avatarUrl);
        const userAvatars = @json($userAvatars ?? []);
        let hasMoreHistory = @json($hasMoreHistory ?? false);
        let oldestMessageId = @json($oldestMessageId ?? null);

        const statusEl = document.getElementById('mqtt-status');
        const historyList = document.getElementById('chat-history-list');
        const form = document.getElementById('chat-form');
        const input = document.getElementById('message-input');
        const fileInput = document.getElementById('chat-file-input');
        const fileBadge = document.getElementById('chat-file-badge');
        const copyInviteBtn = document.getElementById('copy-invite-link-btn');
        const copyInviteFeedback = document.getElementById('copy-invite-link-feedback');
        const loadOlderBtn = document.getElementById('load-older-messages-btn');

        const protocol = mqttUseTls ? 'wss' : 'ws';
        const url = protocol + '://' + mqttHost + ':' + mqttWsPort;
        const clientId = clientIdPrefix + Math.random().toString(16).substr(2, 8);

        let client;
        let notificationPermissionRequested = false;
        const roomItems = document.querySelectorAll('#chat-list li.chat-contact-list-item[data-room-topic]');
        const subscribedTopics = new Set(
          Array.from(roomItems)
            .map(item => item.getAttribute('data-room-topic'))
            .filter(Boolean)
        );
        let newMessagesDividerEl = null;

        if (!mqttTopic) {
          if (statusEl) {
            statusEl.textContent = 'No room selected';
            statusEl.classList.remove('bg-label-success', 'bg-label-warning');
            statusEl.classList.add('bg-label-danger');
          }
          if (form) {
            const submitBtn = form.querySelector('button[type=\"submit\"]');
            if (submitBtn) {
              submitBtn.setAttribute('disabled', 'disabled');
            }
          }

          // Attach leave room confirmation if any leave button exists
          const leaveButtons = document.querySelectorAll('.leave-room-btn');
          leaveButtons.forEach(btn => {
            btn.addEventListener('click', function (e) {
              e.preventDefault();
              const formEl = this.closest('form');
              if (!formEl || !window.Swal) return;

              Swal.fire({
                title: 'Keluar dari room?',
                text: 'Anda tidak akan bisa mengirim pesan di room ini sampai di-invite lagi.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, keluar',
                cancelButtonText: 'Batal'
              }).then(result => {
                if (result.isConfirmed) {
                  formEl.submit();
                }
              });
            });
          });
          return;
        }

        if (loadOlderBtn) {
          if (!hasMoreHistory || !oldestMessageId) {
            loadOlderBtn.classList.add('d-none');
          } else {
            loadOlderBtn.classList.remove('d-none');
          }

          loadOlderBtn.addEventListener('click', function () {
            if (!hasMoreHistory || !oldestMessageId) return;

            loadOlderBtn.disabled = true;
            const originalText = loadOlderBtn.textContent;
            loadOlderBtn.textContent = 'Loading...';

            const params = new URLSearchParams({
              topic: mqttTopic || '',
              before_id: String(oldestMessageId || ''),
            });

            fetch('{{ route('messages.history') }}' + '?' + params.toString(), {
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
              },
            })
              .then((response) => {
                if (!response.ok) {
                  throw new Error('Failed to load history');
                }
                return response.json();
              })
              .then((data) => {
                const msgs = Array.isArray(data.messages) ? data.messages : [];
                if (!msgs.length) {
                  hasMoreHistory = false;
                  loadOlderBtn.classList.add('d-none');
                  return;
                }

                const body = document.getElementById('chat-messages');
                const previousScrollHeight = body ? body.scrollHeight : 0;
                const previousScrollTop = body ? body.scrollTop : 0;

                msgs.forEach((msg) => {
                  addMessage(
                    {
                      text: msg.text || '',
                      attachmentUrl: msg.attachment_url || null,
                      attachmentName: msg.attachment_name || null,
                      attachmentType: msg.attachment_type || null,
                    },
                    msg.sender === currentUser,
                    msg.sender || 'User',
                    true,
                    msg.avatar_url || null
                  );
                });

                if (body) {
                  const newScrollHeight = body.scrollHeight;
                  body.scrollTop = previousScrollTop + (newScrollHeight - previousScrollHeight);
                }

                oldestMessageId = data.next_before_id || oldestMessageId;
                hasMoreHistory = !!data.has_more;

                if (!hasMoreHistory || !oldestMessageId) {
                  loadOlderBtn.classList.add('d-none');
                }
              })
              .catch((err) => {
                console.error(err);
              })
              .finally(() => {
                loadOlderBtn.disabled = false;
                loadOlderBtn.textContent = originalText;
              });
          });
        }

        // Copy invite link
        if (copyInviteBtn) {
          copyInviteBtn.addEventListener('click', function () {
            const url = this.getAttribute('data-invite-url');
            if (!url) return;

            const showFeedback = () => {
              if (!copyInviteFeedback) return;
              copyInviteFeedback.classList.remove('d-none');
              setTimeout(() => {
                copyInviteFeedback.classList.add('d-none');
              }, 2000);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(url).then(showFeedback).catch(showFeedback);
            } else {
              const tmp = document.createElement('input');
              tmp.type = 'text';
              tmp.value = url;
              document.body.appendChild(tmp);
              tmp.select();
              try { document.execCommand('copy'); } catch (_) {}
              document.body.removeChild(tmp);
              showFeedback();
            }
          });
        }

        // Tampilkan nama file yang dipilih
        if (fileInput && fileBadge) {
          fileInput.addEventListener('change', function () {
            const file = this.files && this.files.length ? this.files[0] : null;
            if (!file) {
              fileBadge.textContent = '';
              fileBadge.classList.add('d-none');
              return;
            }

            let label = file.name;
            if (file.size) {
              const kb = file.size / 1024;
              if (kb < 1024) {
                label += ` (${Math.round(kb)} KB)`;
              } else {
                label += ` (${(kb / 1024).toFixed(1)} MB)`;
              }
            }

            fileBadge.textContent = label;
            fileBadge.classList.remove('d-none');
          });
        }

        function ensureNotificationPermission() {
          if (!('Notification' in window)) return;
          if (Notification.permission === 'default' && !notificationPermissionRequested) {
            notificationPermissionRequested = true;
            try {
              Notification.requestPermission().catch(() => {});
            } catch (e) {
              // ignore
            }
          }
        }

        // Render initial history from DB dengan divider "New messages" jika ada pesan baru
        if (Array.isArray(initialMessages) && historyList) {
          const hasLastRead = !!activeLastReadAt;
          let firstNewIndex = -1;
          let lastReadTime = null;
          let unreadCount = 0;

          if (hasLastRead) {
            const ts = Date.parse(activeLastReadAt);
            if (!Number.isNaN(ts)) {
              lastReadTime = ts;
            }
          }

          if (lastReadTime !== null) {
            for (let i = 0; i < initialMessages.length; i++) {
              const msg = initialMessages[i] || {};
              const createdTs = msg.created_at ? Date.parse(msg.created_at) : NaN;
              const fromSelf = (msg.sender === currentUser);

              if (!Number.isNaN(createdTs) && createdTs > lastReadTime && !fromSelf) {
                if (firstNewIndex === -1) {
                  firstNewIndex = i;
                }
                unreadCount++;
              }
            }
          }

          for (let i = 0; i < initialMessages.length; i++) {
            const msg = initialMessages[i] || {};

            if (firstNewIndex !== -1 && i === firstNewIndex && unreadCount > 0) {
              const divider = document.createElement('li');
              divider.className = 'chat-message-divider text-center my-3';
              const label = unreadCount === 1 ? '1 unread message' : `${unreadCount} unread messages`;
              divider.innerHTML = `<span class="badge bg-label-secondary">${label}</span>`;
              historyList.appendChild(divider);
              newMessagesDividerEl = divider;
            }

            addMessage(
              {
                text: msg.text || '',
                attachmentUrl: msg.attachment_url || null,
                attachmentName: msg.attachment_name || null,
                attachmentType: msg.attachment_type || null,
              },
              msg.sender === currentUser,
              msg.sender || 'User',
              true,
              msg.sender === currentUser ? selfAvatarUrl : (msg.avatar_url || null)
            );
          }

          const body = document.getElementById('chat-messages');
          if (body) {
            if (newMessagesDividerEl) {
              const offsetTop = newMessagesDividerEl.offsetTop;
              body.scrollTop = Math.max(offsetTop - 40, 0);

              // Auto-hide divider setelah user berinteraksi (scroll) atau setelah beberapa detik
              const hideDivider = () => {
                if (newMessagesDividerEl && historyList.contains(newMessagesDividerEl)) {
                  newMessagesDividerEl.remove();
                  newMessagesDividerEl = null;
                }
              };

              body.addEventListener('scroll', function onScroll() {
                if (!newMessagesDividerEl) {
                  body.removeEventListener('scroll', onScroll);
                  return;
                }
                const rect = newMessagesDividerEl.getBoundingClientRect();
                const bodyRect = body.getBoundingClientRect();
                if (rect.top < bodyRect.top + 20 || rect.bottom <= bodyRect.top) {
                  hideDivider();
                  body.removeEventListener('scroll', onScroll);
                }
              });

              setTimeout(hideDivider, 15000);
            } else {
              // Tidak ada pesan baru, scroll ke bawah
              body.scrollTop = body.scrollHeight;
            }
          }
        }

        function notifyNewMessage(sender, message) {
          const preview = (message || '').toString();
          const truncated = preview.length > 80 ? preview.slice(0, 77) + '...' : preview;

          // Desktop (Windows) notification via Web Notifications API
          if ('Notification' in window) {
            ensureNotificationPermission();
            if (Notification.permission === 'granted') {
              try {
                new Notification(sender ? `Pesan baru dari ${sender}` : 'Pesan baru', {
                  body: truncated,
                  icon: notificationIcon || undefined
                });
              } catch (e) {
                // ignore
              }
            }
          }
        }

        function incrementUnreadForTopic(topic) {
          // Jangan tampilkan / tambah badge untuk room yang sedang aktif
          if (!topic || !roomItems.length || topic === mqttTopic) return;

          const item = Array.from(roomItems).find(li => li.getAttribute('data-room-topic') === topic);
          if (!item) return;

          let badge = item.querySelector('.room-unread-badge');
          if (!badge) {
            const container = item.querySelector('.chat-contact-info .d-flex.align-items-center');
            if (!container) return;
            badge = document.createElement('span');
            badge.className = 'badge bg-label-primary rounded-pill ms-2 room-unread-badge';
            badge.setAttribute('data-room-topic', topic);
            badge.textContent = '1';
            container.appendChild(badge);
          } else {
            const current = parseInt(badge.textContent || '0', 10) || 0;
            badge.textContent = String(current + 1);
          }
        }

        function addMessage(message, self = false, sender = 'User', noScroll = false, avatarUrl = null) {
          let text = '';
          let attachmentUrl = null;
          let attachmentName = null;
          let attachmentType = null;

          if (message && typeof message === 'object') {
            text = message.text || '';
            attachmentUrl = message.attachmentUrl || null;
            attachmentName = message.attachmentName || null;
            attachmentType = message.attachmentType || null;
          } else {
            text = message || '';
          }

          const li = document.createElement('li');
          li.className = 'chat-message' + (self ? ' chat-message-right' : '');

          const effectiveAvatar =
            avatarUrl ||
            (sender && userAvatars && Object.prototype.hasOwnProperty.call(userAvatars, sender)
              ? userAvatars[sender]
              : null) ||
            (self ? selfAvatarUrl : null);

          let attachmentHtml = '';
          if (attachmentUrl) {
            const safeName = attachmentName || 'Attachment';
            if (attachmentType && attachmentType.startsWith('image/')) {
              attachmentHtml = `
                <div class="mt-2 chat-attachment">
                  <a href="${attachmentUrl}" target="_blank" rel="noopener" class="chat-attachment-link">
                    <img src="${attachmentUrl}" alt="${safeName}" class="img-fluid rounded" style="max-height: 160px;" />
                  </a>
                </div>`;
            } else {
              attachmentHtml = `
                <div class="mt-2 chat-attachment">
                  <a href="${attachmentUrl}" target="_blank" rel="noopener" class="chat-attachment-link">
                    <i class="icon-base ri ri-attachment-2 chat-attachment-icon"></i>
                    <span class="chat-attachment-name">${safeName}</span>
                  </a>
                </div>`;
            }
          }

          const leftAvatarHtml = self
            ? ''
            : `<div class="user-avatar flex-shrink-0 me-4">
                 <div class="avatar avatar-sm">
                   ${
                     effectiveAvatar
                       ? `<img src="${effectiveAvatar}" alt="${sender || 'User'}" class="rounded-circle" />`
                       : `<span class="avatar-initial rounded-circle bg-label-primary">${(sender || 'U').slice(0, 2).toUpperCase()}</span>`
                   }
                 </div>
               </div>`;

          const rightAvatarHtml = self
            ? `<div class="user-avatar flex-shrink-0 ms-4">
                 <div class="avatar avatar-sm">
                   ${
                     effectiveAvatar
                       ? `<img src="${effectiveAvatar}" alt="${sender || 'You'}" class="rounded-circle" />`
                       : `<span class="avatar-initial rounded-circle bg-label-success">Y</span>`
                   }
                 </div>
               </div>`
            : '';

          li.innerHTML = `
            <div class="d-flex overflow-hidden">
              ${leftAvatarHtml}
              <div class="chat-message-wrapper flex-grow-1">
                <div class="chat-message-text">
                  <p class="mb-0"></p>
                  ${attachmentHtml}
                </div>
                <div class="${self ? 'text-end' : ''} text-body-secondary mt-1">
                  <small>${self ? 'You' : sender || 'User'}</small>
                </div>
              </div>
              ${rightAvatarHtml}
            </div>`;
          li.querySelector('p').textContent = text;
          historyList.appendChild(li);
          if (!noScroll) {
            const body = document.getElementById('chat-messages');
            if (body) {
              body.scrollTop = body.scrollHeight;
            }
          }
        }

        function setStatus(text, isError = false) {
          if (!statusEl) return;
          statusEl.textContent = text;
          statusEl.classList.remove('bg-label-success', 'bg-label-warning', 'bg-label-danger');
          statusEl.classList.add(isError ? 'bg-label-danger' : 'bg-label-success');
        }

        try {
          client = mqtt.connect(url, { clientId, clean: true });

          client.on('connect', function () {
            setStatus('Connected');
            const topicsToSubscribe = subscribedTopics.size
              ? Array.from(subscribedTopics)
              : (mqttTopic ? [mqttTopic] : []);

            if (topicsToSubscribe.length) {
              client.subscribe(topicsToSubscribe, function (err) {
                if (err) setStatus('Subscribe error', true);
              });
            }
          });
          client.on('reconnect', function () { setStatus('Reconnecting...', false); });
          client.on('error', function (err) { console.error('MQTT error', err); setStatus('Error', true); });
          client.on('message', function (mqttTopicName, payload) {
            try {
              const text = payload.toString();
              let parsed;
              try { parsed = JSON.parse(text); } catch (_) { parsed = null; }
              const sender = parsed?.sender || 'User';
              const content = parsed?.text ?? text;
              const messageTopic = parsed?.topic || mqttTopicName || mqttTopic;
              const attachmentUrl = parsed?.attachment_url || null;
              const attachmentName = parsed?.attachment_name || null;
              const attachmentType = parsed?.attachment_type || null;
              const avatarUrl = parsed?.avatar_url || null;

              if (!subscribedTopics.has(messageTopic)) return;

              const normalizedSender = (sender || '').toString().trim();
              const normalizedCurrentUser = (currentUser || '').toString().trim();

              // Jangan render pesan yang dikirim oleh diri sendiri (sudah ditangani di sisi pengirim)
              if (normalizedSender && normalizedSender === normalizedCurrentUser) {
                return; // hindari menampilkan sebagai pesan masuk jika pengirim diri sendiri
              }

              if (messageTopic === mqttTopic) {
                addMessage(
                  {
                    text: content,
                    attachmentUrl,
                    attachmentName,
                    attachmentType,
                  },
                  false,
                  sender,
                  false,
                  avatarUrl
                );
              } else {
                incrementUnreadForTopic(messageTopic);
              }

              notifyNewMessage(sender, content);
            } catch (e) { console.error(e); }
          });
        } catch (e) {
          console.error('MQTT connect failed', e);
          setStatus('Connect failed', true);
        }

        // Konfirmasi sebelum keluar room dengan SweetAlert2
        const leaveButtons = document.querySelectorAll('.leave-room-btn');
        leaveButtons.forEach(btn => {
          btn.addEventListener('click', function (e) {
            e.preventDefault();
            const formEl = this.closest('form');
            if (!formEl || !window.Swal) {
              if (formEl) formEl.submit();
              return;
            }

            Swal.fire({
              title: 'Keluar dari room?',
              text: 'Anda tidak akan bisa mengirim pesan di room ini sampai di-invite lagi.',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Ya, keluar',
              cancelButtonText: 'Batal'
            }).then(result => {
              if (result.isConfirmed) {
                formEl.submit();
              }
            });
          });
        });

        if (form) {
          form.addEventListener('submit', function (e) {
            e.preventDefault();
            const msg = (input.value || '').trim();
            const file = fileInput && fileInput.files && fileInput.files.length ? fileInput.files[0] : null;
            if (!msg && !file) return;
            if (!client || !client.connected) return;

            const csrf = document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content');
            // Simpan ke DB lewat API (dengan dukungan file), lalu publish MQTT
            const formData = new FormData();
            formData.append('topic', mqttTopic);
            if (msg) {
              formData.append('text', msg);
            }
            if (file) {
              formData.append('file', file);
            }

            fetch('{{ route('messages.store') }}', {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
              },
              body: formData,
            })
            .then((response) => {
              if (!response.ok) {
                throw new Error('Failed to save message');
              }
              return response.json();
            })
            .then((data) => {
              const payload = JSON.stringify({
                sender: data.sender || currentUser,
                text: data.text || '',
                topic: data.topic || mqttTopic,
                attachment_url: data.attachment_url || null,
                attachment_name: data.attachment_name || null,
                attachment_type: data.attachment_type || null,
                avatar_url: data.avatar_url || null,
              });
              client.publish(mqttTopic, payload);
              addMessage(
                {
                  text: data.text || msg || '',
                  attachmentUrl: data.attachment_url || null,
                  attachmentName: data.attachment_name || (file ? file.name : null),
                  attachmentType: data.attachment_type || (file ? file.type : null),
                },
                true,
                currentUser,
                false,
                data.avatar_url || selfAvatarUrl
              );
              input.value = '';
              if (fileInput) {
                fileInput.value = '';
              }
              if (fileBadge) {
                fileBadge.textContent = '';
                fileBadge.classList.add('d-none');
              }
            })
            .catch((err) => {
              console.error('Failed to save message', err);
            });
          });
        }
      })();
    </script>
  </body>
</html>
