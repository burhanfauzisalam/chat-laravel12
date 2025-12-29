@php
  $mqttHost = $mqttConfig['host'] ?? 'localhost';
  $mqttWsPort = $mqttConfig['ws_port'] ?? 9001;
  $mqttUseTls = $mqttConfig['use_tls'] ?? false;
  $mqttTopic = $activeTopic;
  $clientIdPrefix = $mqttConfig['client_id_prefix'] ?? 'laravel-chat-';
  $currentUser = $currentUser ?? 'User';
  $initialMessages = $messages ?? collect();
  $rooms = $rooms ?? collect();
  $authUser = auth()->user();
  $avatarUrl = $authUser?->avatar_url ?? asset('assets/img/avatars/1.png');
@endphp

<x-layout>
  <x-slot name="title">Chat App - MQTT</x-slot>

  <x-slot name="styles">
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
      }
    </style>
  </x-slot>

  <div class="app-chat card overflow-hidden">
    <div class="row g-0">
      <x-sidebar-left :user="$authUser" :avatarUrl="$avatarUrl" />
      <x-contacts :rooms="$rooms" :mqttTopic="$mqttTopic" :avatarUrl="$avatarUrl" />
      <x-chat-history :mqttTopic="$mqttTopic" :activeRoom="$activeRoom ?? null" :avatarUrl="$avatarUrl" />
      <x-sidebar-right :activeRoom="$activeRoom ?? null" />
      <div class="app-overlay"></div>
    </div>
  </div>

  <x-slot name="modals">
    <x-add-room-modal />
  </x-slot>

  <x-slot name="scripts">
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
        const activeLastReadAt = @json($activeLastReadAt ?? null);
        const notificationIcon = @json(asset('assets/img/favicon/favicon.ico'));
        const selfAvatarUrl = @json($avatarUrl);
        const userAvatars = @json($userAvatars ?? []);
        const geminiTopic = @json(config('services.gemini.topic'));
        const isGeminiTopic = Boolean(mqttTopic && geminiTopic && mqttTopic === geminiTopic);
        const deepseekTopic = @json(config('services.deepseek.topic'));
        const isDeepseekTopic = Boolean(mqttTopic && deepseekTopic && mqttTopic === deepseekTopic);
        const groqTopic = @json(config('services.groq.topic'));
        const isGroqTopic = Boolean(mqttTopic && groqTopic && mqttTopic === groqTopic);
        const dataAssistantTopic = @json(config('services.dataassistant.topic'));
        const isDataAssistantTopic = Boolean(mqttTopic && dataAssistantTopic && mqttTopic === dataAssistantTopic);
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

        // Jika halaman di-load lewat HTTPS, pakai wss untuk menghindari mixed content.
        const protocol = mqttUseTls || window.location.protocol === 'https:' ? 'wss' : 'ws';
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
            const submitBtn = form.querySelector('button[type="submit"]');
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
              .catch((err) => { console.error(err); })
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

        function escapeHtml(str) {
          return (str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        }

        function normalizeHtmlToText(html) {
          if (!html) return '';
          let text = html;

          text = text.replace(/<br\s*\/?>/gi, '\n');
          text = text.replace(/<\/p>\s*<p>/gi, '\n\n');
          text = text.replace(/<\/p>/gi, '\n\n');
          text = text.replace(/<p[^>]*>/gi, '');
          text = text.replace(/<li[^>]*>/gi, '- ');
          text = text.replace(/<\/li>/gi, '\n');
          text = text.replace(/<ul[^>]*>/gi, '\n');
          text = text.replace(/<\/ul>/gi, '\n');
          text = text.replace(/<h[1-6][^>]*>/gi, '\n');
          text = text.replace(/<\/h[1-6]>/gi, '\n');

          text = text.replace(/<[^>]+>/g, '');
          text = text.replace(/\r\n/g, '\n');
          text = text.replace(/\n{3,}/g, '\n\n');

          return text.trim();
        }

        function displayName(sender) {
          if (!sender) return 'User';
          const idx = sender.indexOf('@');
          if (idx > 0) return sender.slice(0, idx);
          return sender;
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
                       ? `<img src="${effectiveAvatar}" alt="${displayName(sender) || 'User'}" class="rounded-circle" />`
                       : `<span class="avatar-initial rounded-circle bg-label-primary">${(displayName(sender) || 'U').slice(0, 2).toUpperCase()}</span>`
                   }
                 </div>
               </div>`;

          const rightAvatarHtml = self
            ? `<div class="user-avatar flex-shrink-0 ms-4">
                 <div class="avatar avatar-sm">
                   ${ 
                     effectiveAvatar
                       ? `<img src="${effectiveAvatar}" alt="${displayName(sender) || 'You'}" class="rounded-circle" />`
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
                  <small>${self ? 'You' : displayName(sender) || 'User'}</small>
                </div>
              </div>
              ${rightAvatarHtml}
            </div>`;
          const textEl = li.querySelector('p');
          const normalized = normalizeHtmlToText(text);
          const safeHtml = escapeHtml(normalized).replace(/\n/g, '<br>');
          textEl.innerHTML = safeHtml;
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

              if (messageTopic === geminiTopic) {
                const isFromCurrentUser = normalizedSender && normalizedSender === normalizedCurrentUser;
                const isFromCurrentGemini = normalizedSender && normalizedCurrentUser && normalizedSender === `Gemini@${normalizedCurrentUser}`;

                if (!isFromCurrentUser && !isFromCurrentGemini) {
                  return;
                }

                if (isFromCurrentUser) {
                  // pesan user sendiri sudah di-render saat kirim
                  return;
                }
              } else {
                // Jangan render pesan yang dikirim oleh diri sendiri (sudah ditangani di sisi pengirim)
                if (normalizedSender && normalizedSender === normalizedCurrentUser) {
                  return; // hindari menampilkan sebagai pesan masuk jika pengirim diri sendiri
                }
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

            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
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
  
              if (isGeminiTopic && (msg || file)) {
                fetch('{{ route('gemini.chat') }}', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                  },
                  body: JSON.stringify({ topic: mqttTopic }),
                })
                  .then((response) =>
                    response
                      .json()
                      .catch(() => ({}))
                      .then((body) => {
                        if (!response.ok) {
                          const message =
                            body.message ||
                            (body.error && body.error.message) ||
                            'Failed to get Gemini response';
                          throw new Error(message);
                        }
                        return body;
                      })
                  )
                  .then((gemini) => {
                    const botPayload = JSON.stringify({
                      sender: gemini.sender || 'Gemini',
                      text: gemini.text || '',
                      topic: gemini.topic || mqttTopic,
                      attachment_url: gemini.attachment_url || null,
                      attachment_name: gemini.attachment_name || null,
                      attachment_type: gemini.attachment_type || null,
                      avatar_url: gemini.avatar_url || null,
                    });
                    client.publish(mqttTopic, botPayload);
                  })
                  .catch((err) => {
                    console.error('Failed to get Gemini response', err);
                    addMessage(
                      {
                        text: 'Gemini error: ' + (err && err.message ? err.message : 'Unknown error'),
                      },
                      false,
                      'Gemini',
                      false,
                      null
                    );
                  });
              }

              if (isDataAssistantTopic && msg) {
                fetch('{{ route('dataassistant.chat') }}', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                  },
                  body: JSON.stringify({ topic: mqttTopic }),
                })
                  .then((response) =>
                    response
                      .json()
                      .catch(() => ({}))
                      .then((body) => {
                        if (!response.ok) {
                          const message =
                            body.message ||
                            (body.error && body.error.message) ||
                            'Failed to get Data Assistant response';
                          throw new Error(message);
                        }
                        return body;
                      })
                  )
                  .then((dataassistant) => {
                    const botPayload = JSON.stringify({
                      sender: dataassistant.sender || 'DataBot',
                      text: dataassistant.text || '',
                      topic: dataassistant.topic || mqttTopic,
                      attachment_url: dataassistant.attachment_url || null,
                      attachment_name: dataassistant.attachment_name || null,
                      attachment_type: dataassistant.attachment_type || null,
                      avatar_url: dataassistant.avatar_url || null,
                    });
                    client.publish(mqttTopic, botPayload);
                  })
                  .catch((err) => {
                    console.error('Failed to get Data Assistant response', err);
                    addMessage(
                      {
                        text: 'Data Assistant error: ' + (err && err.message ? err.message : 'Unknown error'),
                      },
                      false,
                      'DataBot',
                      false,
                      null
                    );
                  });
              }

              if (isDeepseekTopic && msg) {
                fetch('{{ route('deepseek.chat') }}', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                  },
                  body: JSON.stringify({ topic: mqttTopic }),
                })
                  .then((response) =>
                    response
                      .json()
                      .catch(() => ({}))
                      .then((body) => {
                        if (!response.ok) {
                          const message =
                            body.message ||
                            (body.error && body.error.message) ||
                            'Failed to get DeepSeek response';
                          throw new Error(message);
                        }
                        return body;
                      })
                  )
                  .then((deepseek) => {
                    const botPayload = JSON.stringify({
                      sender: deepseek.sender || 'DeepSeek',
                      text: deepseek.text || '',
                      topic: deepseek.topic || mqttTopic,
                      attachment_url: deepseek.attachment_url || null,
                      attachment_name: deepseek.attachment_name || null,
                      attachment_type: deepseek.attachment_type || null,
                      avatar_url: deepseek.avatar_url || null,
                    });
                    client.publish(mqttTopic, botPayload);
                  })
                  .catch((err) => {
                    console.error('Failed to get DeepSeek response', err);
                    addMessage(
                      {
                        text: 'DeepSeek error: ' + (err && err.message ? err.message : 'Unknown error'),
                      },
                      false,
                      'DeepSeek',
                      false,
                      null
                    );
                  });
              }

              if (isGroqTopic && msg) {
                fetch('{{ route('groq.chat') }}', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                  },
                  body: JSON.stringify({ topic: mqttTopic }),
                })
                  .then((response) =>
                    response
                      .json()
                      .catch(() => ({}))
                      .then((body) => {
                        if (!response.ok) {
                          const message =
                            body.message ||
                            (body.error && body.error.message) ||
                            'Failed to get Groq response';
                          throw new Error(message);
                        }
                        return body;
                      })
                  )
                  .then((groq) => {
                    const botPayload = JSON.stringify({
                      sender: groq.sender || 'Groq',
                      text: groq.text || '',
                      topic: groq.topic || mqttTopic,
                      attachment_url: groq.attachment_url || null,
                      attachment_name: groq.attachment_name || null,
                      attachment_type: groq.attachment_type || null,
                      avatar_url: groq.avatar_url || null,
                    });
                    client.publish(mqttTopic, botPayload);
                  })
                  .catch((err) => {
                    console.error('Failed to get Groq response', err);
                    addMessage(
                      {
                        text: 'Groq error: ' + (err && err.message ? err.message : 'Unknown error'),
                      },
                      false,
                      'Groq',
                      false,
                      null
                    );
                  });
              }
            })
            .catch((err) => {
              console.error('Failed to save message', err);
            });
          });
        }
      })();
    </script>
  </x-slot>
</x-layout>
