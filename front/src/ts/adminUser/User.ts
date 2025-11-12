import {getUsers} from './CrudUser.ts'


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



const init = () => {
    showUsers()
};

export default init;