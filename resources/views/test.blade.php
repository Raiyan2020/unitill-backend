<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Particles Working</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background-color: #0f172a;
            overflow: hidden;
        }

        #tsparticles {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .content {
            position: relative;
            z-index: 1;
            padding: 2rem;
            color: white;
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>

<div id="tsparticles"></div>

<div class="content">
    <h1>أحمد العشي</h1>
    <p>مبرمج Laravel من غزة</p>
</div>

<!-- مكتبة tsParticles -->
<script src="https://cdn.jsdelivr.net/npm/tsparticles@3.1.0/tsparticles.bundle.min.js"></script>

<script>
    window.onload = async () => {
        await tsParticles.load("tsparticles", {
            fullScreen: { enable: false },
            particles: {
                number: { value: 80 },
                color: { value: "#ffffff" },
                shape: { type: "circle" },
                opacity: { value: 0.5 },
                size: { value: 3 },
                move: { enable: true, speed: 1 }
            },
            background: {
                color: "#0f172a"
            }
        });
    };
</script>

</body>
</html>
