import {getUserByToken} from "./ts/adminUser/CrudUser.ts";

import routes from "./ts/routes.ts";


const header = document.getElementById('header') as HTMLElement;

const generateHeader = async () => {
    try {
        const response = await getUserByToken();
        const responseData = await response.json();
        const data = responseData.data;
        console.log(data)
        if (data.user) {
            const container = document.createElement('div');
            container.classList.add('profile-container');

            const profileImage = document.createElement('img');
            profileImage.src = data.user.image;
            profileImage.alt = data.user.name;
            profileImage.classList.add('rounded-circle', 'profile-image');

            const menu = document.createElement('div');
            menu.classList.add("drop-menu")

            const ul = document.createElement('ul');
            ul.classList.add('menu-list');

            const items = ['Perfil'];

            if (data.role[0].name == 'admin') {
                items.push('Administrar')
            }

            items.push('Cerrar sesión')

            items.forEach(text => {
                const li = document.createElement('li');
                li.classList.add('menu-item');
                li.textContent = text;

                li.addEventListener('click', (e) => {
                    e.stopPropagation();

                    switch (text) {
                        case 'Perfil':
                            window.location.href = routes.profile;
                            break;

                        case 'Administrar':
                            window.location.href = routes.users;
                            break;

                        case 'Cerrar sesión':
                            sessionStorage.removeItem('token');
                            window.location.href = routes.login;
                            break;
                    }
                });

                ul.appendChild(li);
            });


            menu.appendChild(ul);


            profileImage.addEventListener("click", (e) => {
                e.stopPropagation();
                menu.style.display = menu.style.display === "none" ? "block" : "none";
            });


            document.addEventListener("click", () => {
                menu.style.display = "none";
            });


            container.appendChild(profileImage);
            container.appendChild(menu);
            header.appendChild(container);
        }
    } catch (error) {
        console.error("Error al obtener el usuario:", error);
    }
};

generateHeader();
