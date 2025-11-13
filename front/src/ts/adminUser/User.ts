import {getUsers, addUser} from './CrudUser.ts'


const showUsers = () =>
    loadUsers().then(users => {
        if (users.length === 0) {
            const usersContainer = document.getElementById('usersContainer');
            usersContainer?.classList.add('disabled');

            const usersError = document.getElementById('usersError');
            usersError?.classList.remove('disabled');
        } else {
            const usersContainer = document.getElementById('usersContainer');
            usersContainer?.classList.remove('disabled');

            const usersError = document.getElementById('usersError');
            usersError?.classList.add('disabled');

            chargeTable(users)
        }
    });


const chargeTable = (users: any[]) => {
    document.getElementById('usersTable')!.innerHTML = ""
    users.forEach((user) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-center">
                <img src="${user.image}" alt="${user.name}">
            </td>
            <td class="text-center">${user.name}</td>
            <td class="text-center">${user.nickname}</td>
            <td class="text-center">${user.email}</td>
            <td class="d-flex align-items-center justify-content-center text-center">
                <button class="btn warning">
                    Editar
                </button>
                <button class="btn danger">
                    Eliminar
                </button>
            </td>
        `;
        document.getElementById('usersTable')?.appendChild(row);
    })
}

const loadUsers = async () => {
    try {
        const response = await getUsers();
        const responseData = await response.json();
        return responseData.data;
    } catch(e) {
        console.error("Error al cargar los usuarios:", e);
        return [];
    }
};

const generateModals = () => {
    const body = document.querySelector('body');

    const modal = document.createElement('div');
    modal.innerHTML = `
        <div class="modal">
            <div class="modal-header">
                 <h2>Añadir usuario</h2>
            </div>
            <form action="" method="post" id="addModal">
                <div class="modal-body">
    
                    <div>
                         <label for="name">Nombre: </label>
                        <input type="text" name="name" id="name" required>
                    </div>
                    <div>
                        <label for="nickname">Nickname: </label>
                        <input type="text" name="nickname" id="nickname" required>  
                    </div>
                    <div>
                        <label for="mail">Correo electronico: </label>
                        <input type="text" name="mail" id="mail" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="addBtn" class="btn-close">Agregar</button>
                    <button id="closeAddBtn" class="btn-close">Cerrar</button>
                </div>
            </form>
        </div>
    `

    modal.setAttribute('id', 'modal');
    body!.appendChild(modal);

    const addUserBtn = document.getElementById('addUserBtn');

    addUserBtn!.addEventListener('click', () => {
        modal.style.display = 'flex';
    });

    const closeBtn = document.getElementById('closeAddBtn');
    closeBtn!.addEventListener('click', () => {
        const form = document.getElementById('addModal');
        modal.style.display = 'none';
        form!.reset();
    });

    const addBtn = document.getElementById('addBtn');
    addBtn!.addEventListener('click', async (event) => {
        event.preventDefault();
        const name = document.getElementById('name')!.value
        const nickname = document.getElementById('nickname')!.value
        const mail = document.getElementById('mail')!.value

        const data = {
            "name" : name,
            "nickname" : nickname,
            "email" : mail
        }

        try {
            const response = await addUser(data);

            const responseData = await response.json();
            console.log("Agregado con éxito:", responseData);

        } catch (e) {
            console.error("Error al agregar un usuario:", e);
        }

        const form = document.getElementById('addModal');
        modal.style.display = 'none';
        form!.reset();
        showUsers();
    })
    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

const init = () => {
    showUsers();
    generateModals()
};

export default init;