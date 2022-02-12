<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    @stack('styles')
    <!-- Vue -->
    <script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js"></script>
    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/index.js') }}"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/axios/0.25.0/axios.min.js" integrity="sha512-/Q6t3CASm04EliI1QyIDAA/nDo9R8FQ/BULoUFyN4n/BDdyIxeH7u++Z+eobdmr11gG5D/6nPFyDlnisDwhpYA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/firebase/8.10.1/firebase.js" integrity="sha512-xOm7T+n5pRgOd5Da97/0Tuvgwumw9fca80qIlLDJ7+qCvoJNYTpoNESwL3iJR9m8Klrf2W9ocgcTSP60ZUKJvA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/firebase/8.10.1/firebase-messaging.min.js" integrity="sha512-v5yEhqjlpSupFcjvkEP+AloVEjQBd/CK0pysyAw/YCChyiq54FUuucx2N9oACFBi1qHXsAuhOMsoHiFYxIXCMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        const firebaseConfig = {

            apiKey: "AIzaSyBE90OnfKk6MELQ2-Z_DVLYp4maXVzvQO8",

            authDomain: "ninja-firestore-tut-nov11.firebaseapp.com",

            databaseURL: "https://ninja-firestore-tut-nov11-default-rtdb.asia-southeast1.firebasedatabase.app",

            projectId: "ninja-firestore-tut-nov11",

            storageBucket: "ninja-firestore-tut-nov11.appspot.com",

            messagingSenderId: "28680409583",

            appId: "1:28680409583:web:66bef6c94232b88816e61a"

        };
        firebase.initializeApp(firebaseConfig);
    </script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{asset("firebase-messaging-sw.js")}}')
            });
        }
    </script>

</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100">
        @include('layouts.navigation')

        <!-- Page Heading -->
        <!-- <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                {-- $header --}
            </div>
        </header> -->

        <!-- Page Content -->
        <!-- <main>
            {-- $slot --}
        </main> -->

        @yield('content')
    </div>

    @stack('scripts')
</body>

</html>