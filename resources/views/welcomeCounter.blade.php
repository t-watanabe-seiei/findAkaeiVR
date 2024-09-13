<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Alpine Ajax test</title>
    </head>
    <body>
        <h1>Users API Retrieval Test</h1>

        <div x-data="createDataRetriever()" x-init="getData()">
            Scores LIST
            <ul>
                <template x-for="score in scores" :key="score.id">                    
                    <li>                        
                        <span class="" x-text="score.id"></span>
                        <span class="" x-text="score.userid"></span>   
                        <span class="" x-text="score.time"></span>                         
                    </li>
                </template>
            </ul>
        </div>

        <script src="//unpkg.com/alpinejs" defer></script>
        <script>

            /**
             * Alpine instance with data retrieval (getData)
             */  
            function createDataRetriever() {

                return {
                    isLoading: false,
                    scores: [],
                    getData() {
                        this.isLoading = true;
                        fetch('http://localhost:8000/api/Scores/20230001')            
                            .then((response) => response.json())
                            .then((data) => { 
                                this.scores = data;
                                this.isLoading = false;
                            });
                    }  
                }
            }

        </script>
    </body>
</html>