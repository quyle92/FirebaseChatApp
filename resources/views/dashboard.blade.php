@extends('layouts.app')
@section('content')
<header class="bg-white shadow">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </div>
</header>

<div class="py-12" id="chatForm">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column ">
                            <template v-for="(item, index) in messageList">
                                <small :class="[item.isOwner !== 1 ? 'chat-left' : 'chat-right']">@{{item.player_name}} - (@{{item.updated_at ? new Date(item.updated_at.seconds * 1000) : new Date(item.created_at.seconds * 1000)}})</small>
                                <p class="chat" :class="[item.isOwner !== 1 ? 'chat-left' : 'chat-right bg-info text-white']" :key="index" @contextmenu="openMenu($event, item.created_at,index)">@{{item.message}}</p>
                            </template>
                            <p class="mx-auto" v-if="messageList.length === 0">No chat messages.</p>
                            <ul id="right-click-menu" tabindex="-1" v-if="viewMenu" :style="{top:top, left:left}" v-click-outside="hideMenu">
                                <li @click="editMessage">Edit</li>
                                <li @click="removeMessage">Remove</li>
                            </ul>
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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.9.0/css/all.min.css" />
<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-firestore.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/firebase/8.10.1/firebase-auth.min.js"></script>

<script type="text/javascript">
    const messaging = firebase.messaging();
    const db = firebase.firestore();
    const team_sn = '{{Auth::user()->team_sn}}';
    const player_sn = '{{Auth::id()}}';
    const player_name = '{{Auth::user()->player_name}}';
    const jwt = '{{$jwt}}';
    // console.log(jwt)
    const contextMenu = document.getElementById("right-click-menu");

    var unsubscribe = firebase.auth().onAuthStateChanged(function(user) {
        if (user) {
            //retrieve IdToken: https: //firebase.google.com/docs/auth/admin/verify-id-tokens#retrieve_id_tokens_on_clients
            // user.getIdToken().then(idToken => console.log('idToken: ' + idToken))
        } else {
            firebase.auth().signInWithCustomToken(jwt)
                .then((userCredential) => {
                    // Signed in
                    // console.log('refreshToken: ' + userCredential.user.refreshToken)
                })
                .catch((error) => {
                    var errorCode = error.code;
                    var errorMessage = error.message;
                    // ...
                    console.log(error)
                });
            // see docs for a list of available properties
            // https://firebase.google.com/docs/reference/js/firebase.User
        }
    });

    var app = new Vue({
        el: '#chatForm',
        data: {
            messageList: [],
            message: 'Hello Vue!',
            fcmToken: '',
            chatNotice: '',
            viewMenu: false,
            top: '0px',
            left: '0px',
            chatId: '',
            chatIndex: '',
            action: 'create'
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
                // console.log('startWritting')
                axios.post('api/start-writting', {
                    fcmToken: this.fcmToken,
                })
                this.chatNotice = '';
            }, 500, true),

            stopWriting: debounce(function() {
                // console.log('stopWriting')
                axios.post('api/stop-writting', {
                    fcmToken: this.fcmToken,
                })
                this.chatNotice = '';
            }, 300),

            fetchMessages() {
                db.collection("chats").doc(team_sn)
                    .onSnapshot((doc) => {
                        if (doc.exists) {
                            let sortable = [];
                            for (let x in doc.data()) {
                                sortable.push(doc.data()[x]);
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
                        } else {
                            this.messageList = [];
                        }
                    });
            },

            submitMessage(e) {
                let chatAt = new Date();
                let data = new Map([
                    ['player_sn', player_sn],
                    ['player_name', player_name],
                    ['message', this.message],
                ]);

                if (this.action === "create") {
                    // console.log(this.action)
                    let uuid = Math.floor(new Date().getTime() / 1000);
                    let obj = {};
                    data.set('created_at', chatAt)
                    obj[uuid] = Object.fromEntries(data);

                    db.collection("chats").doc(team_sn).set(obj, {
                            merge: true
                        })
                        .then(() => {
                            console.log("Document successfully written!");
                        })
                        .catch((error) => {
                            console.error("Error writing document: ", error);
                        });
                }

                if (this.action === "edit") {
                    // console.log(this.action)
                    data.set('created_at', this.messageList[this.chatIndex].created_at)
                    data.set('updated_at', chatAt);
                    let obj = {};
                    obj[this.chatId] = Object.fromEntries(data);
                    db.collection("chats").doc(team_sn).set(obj, {
                        merge: true
                    }).then(() => {
                        console.log("Document updated successfully!");
                    }).catch((error) => {
                        console.error("Error updating document: ", error);
                    });
                    this.action = 'create'
                }

                this.message = '';
            },

            editMessage: function() {
                this.action = "edit";
                this.hideMenu();
                this.message = this.messageList[this.chatIndex].message;
            },

            removeMessage: function() {
                let obj = {};
                obj[this.chatId] = firebase.firestore.FieldValue.delete();
                db.collection("chats").doc(team_sn).update(obj).then(() => {
                    console.log("Document successfully deleted!");
                }).catch((error) => {
                    console.error("Error removing document: ", error);
                });
                this.hideMenu();
            },

            removeAllChat() {
                db.collection("chats").doc(team_sn).delete().then(() => {
                    console.log("Document successfully deleted!");
                }).catch((error) => {
                    console.error("Error removing document: ", error);
                });
            },



            setMenu: function(top, left) {

                largestHeight = window.innerHeight - contextMenu.offsetHeight - 25;
                largestWidth = window.innerWidth - contextMenu.offsetWidth - 25;

                if (top > largestHeight) top = largestHeight;

                if (left > largestWidth) left = largestWidth;

                this.top = top + 'px';
                this.left = left + 'px';
            },

            hideMenu: function() {
                this.viewMenu = false;
            },

            openMenu: function(e, chatId, chatIndex) {
                this.viewMenu = true;
                this.chatId = chatId.seconds;
                this.chatIndex = chatIndex;

                Vue.nextTick(function() {
                    contextMenu.focus();

                    this.setMenu(e.y, e.x)
                }.bind(this));
                e.preventDefault();
            }
        },

        created() {
            messaging.getToken({
                vapidKey: '{{config("fcm.VAPID")}}'
            }).then((currentToken) => {
                // console.log(currentToken)
                if (currentToken) {
                    // this.sendTokenToServer(currentToken);
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

        beforeDestroy() {
            this.stopWriting();
        },

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

    #right-click-menu {
        background: #FAFAFA;
        border: 1px solid #BDBDBD;
        box-shadow: 0 2px 2px 0 rgba(0, 0, 0, .14), 0 3px 1px -2px rgba(0, 0, 0, .2), 0 1px 5px 0 rgba(0, 0, 0, .12);
        display: block;
        list-style: none;
        margin: 0;
        padding: 0;
        position: fixed;
        width: 250px;
        z-index: 999999;
    }

    #right-click-menu li {
        border-bottom: 1px solid #E0E0E0;
        margin: 0;
        padding: 5px 35px;
    }

    #right-click-menu li:last-child {
        border-bottom: none;
    }

    #right-click-menu li:hover {
        background: #1E88E5;
        color: #FAFAFA;
    }
</style>
@endpush

<!--
Notes:
//(1): difference b/t custom token (JWT), which is generated by your own server, and IdToken,
which is generated by Firebase: https://medium.com/@jwngr/demystifying-firebase-auth-tokens-e0c533ed330c
https://firebase.google.com/docs/auth/users/#auth_tokens
-->