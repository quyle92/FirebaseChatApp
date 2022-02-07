@extends('layouts.app')
@section('content')
<header class="bg-white shadow">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </div>
</header>
@php

@endphp
<div class="py-12" id="chatForm">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column ">
                            <!-- @forelse ($chats as $chat)
                            @if($chat->player_sn !== Auth::id())
                            <p class="chat chat-left">{{$chat->message}}</p>
                            <small class="chat-left">{{$chat->player_name}}</small>
                            @else
                            <p class="chat chat-right bg-info text-white">{{$chat->message}}</p>
                            <small class="chat-right">You - {{$chat->player_name}}</small>
                            @endif
                            @empty
                            <p class="mx-auto">No chat messages.</p>
                            @endforelse -->
                        </div>
                        <small v-html="chatNotice"></small>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mt-5">
            <form class="p-3" method="post">
                @csrf
                <div class="form-group">
                    <label for="message">Message</label>
                    <input type="text" class="form-control" id="message" name="message" v-model="message" @input="startWritting" @blur="stopWriting">
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.9.0/css/all.min.css" />
<script type="text/javascript">
    const messaging = firebase.messaging();
    const player_sn = '{{Auth::id()}}';

    var app = new Vue({
        el: '#chatForm',
        data: {
            messageList: [],
            message: 'Hello Vue!',
            fcmToken: '',
            chatNotice: ''
        },
        methods: {
            sendTokenToServer(fcm_token) {
                axios.post('api/save-token', {
                        fcm_token,
                        player_sn
                    })
                    .then(function(response) {
                        // console.log(response);
                    })
                    .catch(function(error) {
                        console.log(error);
                    });
            },
            startWritting() {
                axios.post('api/start-writting', {
                    fcmToken: this.fcmToken,
                })
            },
            stopWriting() {
                axios.post('api/stop-writting', {
                    fcmToken: this.fcmToken,
                })
                this.user = '';
                this.activeChat = 'none';
            },
            async fetchMessages() {
                let response = await axios.get('api/messages');
                console.log(response);
                this.messageList = response.data;
            }
        },
        created() {
            messaging.getToken({
                vapidKey: '{{config("fcm.VAPID")}}'
            }).then((currentToken) => {
                // console.log(currentToken)
                if (currentToken) {
                    this.sendTokenToServer(currentToken);
                    this.fcmToken = currentToken
                } else {
                    // Show permission request UI
                    console.log('No registration token available. Request permission to generate one.');
                    // ...
                }
            }).catch((err) => {
                console.log('An error occurred while retrieving token. ', err);
                // ...
            });

            messaging.onMessage((payload) => {
                // console.log('onMessage');
                // console.log(payload);
                if (player_sn !== payload.data.player.player_sn) {
                    if (payload.data.action === 'write') {
                        this.chatNotice = '<b><i class="fas fa-pen"></i> ' + JSON.parse(payload.data.player).player_name + ' is writting </b>';
                    }
                    if (payload.data.action === 'stop') {
                        this.chatNotice = ''
                    }
                }

            });

            this.fetchMessages();
        }
    })
</script>
@endpush
@push('styles')
<style>
    .chat {
        border: 1px solid gray;
        border-radius: 3px;
        width: 50%;
        padding: 0.5em;
    }

    .chat-left {
        align-self: flex-start;
    }

    .chat-right {
        align-self: flex-end;
    }
</style>
@endpush