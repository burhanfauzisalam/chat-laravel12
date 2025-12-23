@props(['mqttTopic', 'activeRoom', 'avatarUrl'])

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
