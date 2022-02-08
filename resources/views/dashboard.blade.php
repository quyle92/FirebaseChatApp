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
                            <template v-for="(item, index) in messageList">
                                <p class="chat" :class="[item.isOwner !== 1 ? 'chat-left' : 'chat-right bg-info text-white']" :key="index">@{{item.message}}</p>
                                <small :class="[item.isOwner !== 1 ? 'chat-left' : 'chat-right']">@{{item.player_name}}</small>
                            </template>
                            <p class="mx-auto" v-if="messageList.length === 0">No chat messages.</p>
                        </div>
                        <small v-html="chatNotice"></small>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mt-5">
            <form class="p-3" @submit.prevent="submitMessage">
                @csrf
                <div class="form-group">
                    <label for="message">Message</label>
                    <input type="text" class="form-control" id="message" name="message" v-model="message" @input="startWritting" @keyup="stopWriting">
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
                <button class="btn btn-danger float-right" @click.prevent="removeAllChat">Remove All Chat</button>
            </form>

        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.9.0/css/all.min.css" />
<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-firestore.js"></script>
<script type="text/javascript">
    const messaging = firebase.messaging();
    const db = firebase.firestore();
    const team_sn = '{{Auth::user()->team_sn}}';
    const player_sn = '{{Auth::id()}}';
    const player_name = '{{Auth::user()->player_name}}';

    var app = new Vue({
        el: '#chatForm',
        data: {
            messageList: [],
            message: 'Hello Vue!',
            fcmToken: '',
            chatNotice: '',
            count: 0

        },
        computed: {

        },
        methods: {
            sendTokenToServer(fcm_token) {
                axios.post('api/save-token', {
                        fcm_token: fcm_token,
                        player_sn: player_sn
                    })
                    .then(function(response) {
                        // console.log(response);
                    })
                    .catch(function(error) {
                        console.log(error);
                    });
            },
            startWritting: debounce(function() {
                console.log('startWritting')
                axios.post('api/start-writting', {
                    fcmToken: this.fcmToken,
                })
                this.chatNotice = '';
            }, 500, true),
            stopWriting: debounce(function() {
                axios.post('api/stop-writting', {
                    fcmToken: this.fcmToken,
                })
                this.chatNotice = '';
            }, 500),
            fetchMessages() {
                db.collection("chat").doc(team_sn)
                    .onSnapshot((doc) => {
                        if (doc.exists) {
                            let sortable = [];
                            for (let vehicle in doc.data()) {
                                sortable.push(doc.data()[vehicle]);
                            }
                            sortable.sort(function(a, b) {
                                return a.time - b.time;
                            });
                            let data = [];
                            sortable.forEach((item) => {
                                if (item.player_sn === player_sn) {
                                    item.isOwner = 1;
                                } else {
                                    item.isOwner = 0;
                                }
                                data.push(item)
                            });
                            this.messageList = data;
                        }
                    });
            },
            submitMessage(e) {
                let data = new Map([
                    ['player_sn', player_sn],
                    ['player_name', player_name],
                    ['message', this.message],
                    ['time', new Date()]
                ]);
                let uuid = (new Date()).getTime();
                this.message = '';
                let obj = {};
                obj[uuid] = Object.fromEntries(data);
                // console.log(obj)
                db.collection("chat").doc(team_sn).set(obj, {
                        merge: true
                    })
                    .then(() => {
                        console.log("Document successfully written!");
                    })
                    .catch((error) => {
                        console.error("Error writing document: ", error);
                    });
            },
            removeAllChat() {
                db.collection("chat").doc(team_sn).delete().then(() => {
                    console.log("Document successfully deleted!");
                    this.messageList = []
                }).catch((error) => {
                    console.error("Error removing document: ", error);
                });
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
                        this.chatNotice = '<b><i class="fas fa-pen"></i> ' + JSON.parse(payload.data.player).player_name + ' is writting. </b>';
                        // setTimeout(() => { this.chatNotice = ''}, 500);
                    }
                    if (payload.data.action === 'stop') {
                        console.log('stop writing');
                        this.chatNotice = ''
                    }
                }

            });

            this.fetchMessages();
            // this.stopWriting(); //remove 'is writting' notice on page load.
        },
        // beforeDestroy() {
        //     this.stopWriting();
        // }
    })

    // Returns a function, that, as long as it continues to be invoked, will not
    // be triggered. The function will be called after it stops being called for
    // N milliseconds. If `immediate` is passed, trigger the function on the
    // leading edge, instead of the trailing.
    function debounce(func, wait, immediate) {

        var timeout;
        return function() {
            var context = this,
                args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    };
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